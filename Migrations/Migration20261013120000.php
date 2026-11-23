<?php

namespace Plugin\endereco_jtl5_client\Migrations;

use JTL\DB\DbInterface;
use JTL\Plugin\Migration;
use JTL\Services\JTL\CryptoServiceInterface;
use JTL\Shop;
use JTL\Update\IMigration;
use Plugin\endereco_jtl5_client\src\Helper\AddressDataCipher;
use stdClass;
use Throwable;

class Migration20261013120000 extends Migration implements IMigration
{
    private const BATCH_SIZE = 500;
    private const SUMMARY_ID_LIMIT = 20;

    /** @var array<string, int[]> Ids of the removed rows by reason. */
    private array $removedIds = [];

    public function up()
    {
        // Predictions stored before this release keep street and additional
        // info readable in a database dump. Encrypt them in place, so existing
        // customers are covered without a new address check. Values that are
        // already encrypted are left alone, which makes a repeated run harmless.
        // A row that cannot be encrypted is removed: personal data never stays
        // readable, and the address counts as never checked.
        $cipher = $this->createCipher();
        $this->processRows(fn (DbInterface $db, stdClass $row) => $this->encryptRow($db, $cipher, $row));
        $this->reportSummary();
    }

    public function down()
    {
        // The previous release cannot read encrypted predictions, and personal data
        // is never written back readable. Rows holding encrypted values are removed,
        // the previous release checks these addresses again. Rows without them hold
        // no personal data and are kept. The prefix is the one of AddressDataCipher.
        $this->execute(
            "DELETE FROM `xplugin_endereco_jtl5_client_tams`
             WHERE `enderecoamspredictions` LIKE '%endereco:xtea:%'"
        );
    }

    private function createCipher(): AddressDataCipher
    {
        /** @var CryptoServiceInterface $cryptoService */
        $cryptoService = Shop::Container()->getCryptoService();

        return new AddressDataCipher($cryptoService);
    }

    /**
     * Encrypts the personal fields of one row in place. A row without readable
     * personal fields, because they are already encrypted or absent, is left
     * unchanged. A row whose predictions are no valid JSON or cannot be
     * encrypted is removed instead, so no personal data stays readable.
     */
    private function encryptRow(DbInterface $db, AddressDataCipher $cipher, stdClass $row): void
    {
        $id = (int) $row->id;
        $predictions = json_decode((string) $row->enderecoamspredictions, true);
        if (!is_array($predictions)) {
            $this->removeRow($db, $id, 'the stored predictions are no valid JSON');
            return;
        }

        $encrypted = $cipher->encryptPredictions($predictions);
        if ($encrypted === null) {
            $this->removeRow($db, $id, 'the predictions could not be encrypted');
            return;
        }

        if ($encrypted === $predictions) {
            return;
        }

        $json = json_encode($encrypted);
        if ($json === false) {
            $this->removeRow($db, $id, 'the encrypted predictions could not be encoded');
            return;
        }

        $db->update('xplugin_endereco_jtl5_client_tams', 'id', $id, (object) ['enderecoamspredictions' => $json]);
    }

    /**
     * Passes every row of the table to the handler.
     *
     * Nothing is thrown here: a failed plugin update makes the shop uninstall
     * the plugin, which drops the whole table. A row whose handling fails is
     * removed, so personal data never stays readable because of an error.
     *
     * Every batch is committed on its own. A single transaction over a large
     * table would lock every rewritten row until the end, so the shop could
     * not write the address meta of these customers for minutes, and an
     * aborted run would roll back all progress. Committed batches keep their
     * state, and a repeated run skips them quickly.
     *
     * @param callable(DbInterface, stdClass): void $handler
     */
    private function processRows(callable $handler): void
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
                        $handler($db, $row);
                    } catch (Throwable $e) {
                        $this->removeRow($db, $lastId, $e->getMessage());
                    }
                }

                $this->commitBatch($db);
            } while (count($rows) === self::BATCH_SIZE);
        } catch (Throwable $e) {
            $this->logError('Endereco: processing the stored address check results stopped early: ' . $e->getMessage());
        }
    }

    /**
     * Nothing is logged per row, see reportSummary().
     */
    private function removeRow(DbInterface $db, int $id, string $reason): void
    {
        $this->removedIds[$reason][] = $id;

        try {
            $db->delete('xplugin_endereco_jtl5_client_tams', 'id', $id);
        } catch (Throwable) {
            // The shop's database layer does not throw on a failed query, it logs the error
            // itself (ES_DB_LOGGING, on by default).
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

    /**
     * Logged as an error, because the shop's default log level shows errors
     * only. Logging never throws, so the migration is not aborted.
     */
    private function logError(string $message): void
    {
        try {
            Shop::Container()->getLogService()->error($message);
        } catch (Throwable) {
            // Logging is best effort during a migration.
        }
    }

    /**
     * Logs one entry per reason instead of one per row. A run where every row
     * fails, e.g. without BLOWFISH_KEY, would otherwise write an entry for every
     * row of a possibly large table.
     */
    private function reportSummary(): void
    {
        foreach ($this->removedIds as $reason => $ids) {
            $this->logError(
                'Endereco: ' . count($ids) . ' rows of xplugin_endereco_jtl5_client_tams were removed by the'
                . ' update, because processing them failed: ' . $reason . ' (ids: ' . $this->listIds($ids) . ').'
                . ' No personal data stays readable, the affected addresses count as never checked.'
            );
        }
    }

    /**
     * @param int[] $ids
     */
    private function listIds(array $ids): string
    {
        $listedIds = implode(', ', array_slice($ids, 0, self::SUMMARY_ID_LIMIT));

        return count($ids) > self::SUMMARY_ID_LIMIT ? $listedIds . ', ...' : $listedIds;
    }
}
