<?php

namespace Plugin\endereco_jtl5_client\Migrations;

use JTL\DB\DbInterface;
use JTL\DB\ReturnType;
use JTL\Plugin\Migration;
use JTL\Services\JTL\CryptoServiceInterface;
use JTL\Shop;
use JTL\Update\IMigration;
use Plugin\endereco_jtl5_client\src\Helper\AddressDataCipher;
use Throwable;

class Migration20261119120000 extends Migration implements IMigration
{
    private const BATCH_SIZE = 500;

    public function up()
    {
        // An address check result is usable only if it is valid and holds no
        // readable personal data, see AddressDataCipher::readStoredPredictions().
        // Rows the previous migration could not encrypt, and any other row that
        // is not in the expected format, are removed once from the whole table,
        // so no personal data stays readable for customers who never return.
        // Their addresses count as never checked. Only the format is checked,
        // without decrypting, so this stays fast on large tables. Rows that do
        // not decrypt with the current key are removed when they are loaded.
        /** @var CryptoServiceInterface $cryptoService */
        $cryptoService = Shop::Container()->getCryptoService();
        $this->removeInvalidRows(new AddressDataCipher($cryptoService));
    }

    public function down()
    {
        // Intentionally a no-op: removed rows held no usable check result.
    }

    /**
     * Nothing is thrown here: a failed plugin update makes the shop uninstall
     * the plugin, which drops the whole table. Every batch is committed on its
     * own, so an aborted run keeps its progress and a repeated run goes on.
     */
    private function removeInvalidRows(AddressDataCipher $cipher): void
    {
        $db = $this->getDB();
        $lastId = 0;
        $removedCount = 0;
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

                $invalidIds = [];
                foreach ($rows as $row) {
                    $lastId = (int) $row->id;
                    if (!$cipher->isStoredFormatValid((string) $row->enderecoamspredictions)) {
                        $invalidIds[] = $lastId;
                    }
                }

                $removedCount += $this->deleteRows($db, $invalidIds);
                $this->commitBatch($db);
            } while (count($rows) === self::BATCH_SIZE);
        } catch (Throwable $e) {
            $this->log('Endereco: removing invalid address check results stopped early: ' . $e->getMessage());
        }

        if ($removedCount > 0) {
            $this->log(
                'Endereco: ' . $removedCount . ' stored address check results were removed by the update,'
                . ' because they were not in the expected format or held personal fields unencrypted.'
                . ' The affected addresses are checked again wherever checks are enabled.'
            );
        }
    }

    /**
     * @param int[] $ids
     *
     * @return int The number of removed rows.
     */
    private function deleteRows(DbInterface $db, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $placeholders[] = ':id' . $index;
            $params[':id' . $index] = $id;
        }

        $affectedRows = $db->queryPrepared(
            'DELETE FROM `xplugin_endereco_jtl5_client_tams` WHERE `id` IN (' . implode(', ', $placeholders) . ')',
            $params,
            ReturnType::AFFECTED_ROWS
        );

        return is_int($affectedRows) ? $affectedRows : 0;
    }

    /**
     * Commits the rows removed so far and opens the next transaction. The shop
     * runs every migration in a transaction and commits whatever is still open
     * at the end.
     */
    private function commitBatch(DbInterface $db): void
    {
        if ($db->getPDO()->inTransaction()) {
            $db->commit();
            $db->beginTransaction();
        }
    }

    /**
     * The table may be large, and the migration runs within the request of the
     * plugin update. The PHP time limit is lifted so it does not stop the run.
     * Hosts may disable that or end the request through the web server, the
     * committed batches then keep the progress for the next run.
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
    private function log(string $message): void
    {
        try {
            Shop::Container()->getLogService()->error($message);
        } catch (Throwable) {
            // Logging is best effort during a migration.
        }
    }
}
