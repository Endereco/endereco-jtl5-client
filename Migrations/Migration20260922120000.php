<?php

namespace Plugin\endereco_jtl5_client\Migrations;

use JTL\Plugin\Migration;
use JTL\Shop;
use JTL\Update\IMigration;
use Throwable;

class Migration20260922120000 extends Migration implements IMigration
{
    /**
     * Files of the removed browser relay, relative to the plugin directory.
     */
    private const OBSOLETE_FILES = [
        'io.php',
        '.htaccess',
    ];

    public function up()
    {
        // The shop deletes files that disappeared from a release by diffing
        // .old_version_files against .current_version_files, and it skips that
        // step when either inventory is missing. A plugin directory deployed by
        // hand never has one, so io.php would survive the update and keep
        // relaying to the Endereco API with a caller supplied key and without a
        // request token, while the orphaned .htaccess keeps granting access to
        // it. Both are removed here so the outcome no longer depends on how the
        // plugin was installed.
        $pluginDirectory = dirname(__DIR__);

        foreach (self::OBSOLETE_FILES as $fileName) {
            $path = $pluginDirectory . '/' . $fileName;
            if (!is_file($path)) {
                continue;
            }

            // Suppressed on purpose: a failed unlink must not abort the update,
            // because the shop would then stay on the release that still ships
            // the relay. The failure is reported instead.
            if (!@unlink($path)) {
                $this->reportFailure($path);
            }
        }
    }

    public function down()
    {
        // Intentionally a no-op. Restoring the relay would reintroduce an
        // unauthenticated proxy to the Endereco API.
    }

    private function reportFailure(string $path): void
    {
        try {
            Shop::Container()->getLogService()->warning(
                'Endereco: could not delete obsolete browser relay file ' . $path
            );
        } catch (Throwable) {
            // Logging is best effort during a migration.
        }
    }
}
