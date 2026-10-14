<?php

namespace Plugin\endereco_jtl5_client\Migrations;

use JTL\DB\DbInterface;
use JTL\Plugin\Migration;
use JTL\Services\JTL\CryptoServiceInterface;
use JTL\Shop;
use JTL\Update\IMigration;
use Plugin\endereco_jtl5_client\src\Helper\AddressDataCipher;
use Throwable;

class Migration20261013120000 extends Migration implements IMigration
{
    private const BATCH_SIZE = 500;

    public function up()
    {
        // Predictions stored before this release keep street and additional
        // info readable in a database dump. Encrypt them in place, so existing
        // customers are covered without a new address check. Values that are
        // already encrypted are left alone, which makes a repeated run harmless.
        $cipher = $this->createCipher();
        $this->rewritePredictions(
            static fn (array $predictions): array => $cipher->encryptPredictions($predictions)
        );
    }

    public function down()
    {
        // The previous release expects the predictions in plain text. Rows that
        // cannot be decrypted with the current key stay encrypted and are reported.
        $cipher = $this->createCipher();
        $this->rewritePredictions(
            static fn (array $predictions): ?array => $cipher->decryptPredictions($predictions)
        );
    }

    private function createCipher(): AddressDataCipher
    {
        /** @var CryptoServiceInterface $cryptoService */
        $cryptoService = Shop::Container()->getCryptoService();

        return new AddressDataCipher($cryptoService);
    }

    /**
     * Applies the transformation to the predictions of every row.
     *
     * Checked address data is never deleted here, and nothing is thrown: a
     * failed plugin update makes the shop uninstall the plugin, which drops the
     * whole table. A row that cannot be transformed is left as it is and
     * reported instead.
     *
     * Every batch is committed on its own. A single transaction over a large
     * table would lock every rewritten row until the end, so the shop could
     * not write the address meta of these customers for minutes, and an
     * aborted run would roll back all progress. Committed batches stay
     * encrypted, and a repeated run skips them quickly.
     *
     * @param callable(mixed[]): (mixed[]|null) $transform
     */
    private function rewritePredictions(callable $transform): void
    {
        $db = $this->getDB();
        $lastId = 0;
        $this->liftTimeLimit();

        try {
            do {
                $rows = $db->getObjects(
                    'SELECT `id`, `enderecoamspredictions`
                        FROM `xplugin_endereco_jtl5_client_tams`
                        WHERE `id` > :lastId
                        ORDER BY `id`
                        LIMIT ' . self::BATCH_SIZE,
                    [':lastId' => $lastId]
                );

                foreach ($rows as $row) {
                    $lastId = (int) $row->id;

                    try {
                        $predictions = json_decode((string) $row->enderecoamspredictions, true);
                        if (!is_array($predictions)) {
                            $this->reportFailure($lastId, 'the stored predictions are no valid JSON');
                            continue;
                        }

                        $transformed = $transform($predictions);
                        if ($transformed === null) {
                            $this->reportFailure($lastId, 'the predictions could not be transformed with the current key');
                            continue;
                        }

                        if ($transformed === $predictions) {
                            continue;
                        }

                        $json = json_encode($transformed);
                        if ($json === false) {
                            $this->reportFailure($lastId, 'the transformed predictions could not be encoded');
                            continue;
                        }

                        $db->update(
                            'xplugin_endereco_jtl5_client_tams',
                            'id',
                            $lastId,
                            (object) ['enderecoamspredictions' => $json]
                        );
                    } catch (Throwable $e) {
                        $this->reportFailure($lastId, $e->getMessage());
                    }
                }

                $this->commitBatch($db);
            } while (count($rows) === self::BATCH_SIZE);
        } catch (Throwable $e) {
            $this->reportFailure($lastId, 'reading the rows failed: ' . $e->getMessage());
        }
    }

    /**
     * Commits the rows rewritten so far and opens the next transaction. The
     * shop runs every migration in a transaction and commits whatever is still
     * open at the end.
     */
    private function commitBatch(DbInterface $db): void
    {
        if ($db->getPDO()->inTransaction()) {
            $db->commit();
            $db->beginTransaction();
        }
    }

    /**
     * Encrypting a large table takes minutes, within the request of the plugin update.
     * The PHP time limit is lifted so it does not stop the run.
     * Hosts may disable that or end the request through the web server,
     * the committed batches then keep the progress for the next run.
     */
    private function liftTimeLimit(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
    }

    private function reportFailure(int $id, string $reason): void
    {
        try {
            Shop::Container()->getLogService()->warning(
                'Endereco: the address predictions of row ' . $id . ' were left unchanged, ' . $reason
            );
        } catch (Throwable) {
            // Logging is best effort during a migration.
        }
    }
}
