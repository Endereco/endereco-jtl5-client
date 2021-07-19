<?php
namespace Plugin\endereco_jtl5_client\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

class Migration20210329174800 extends Migration implements IMigration
{
    public function up()
    {
        $this->execute("CREATE TABLE IF NOT EXISTS `xplugin_endereco_jtl5_client_tams` (
          `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `kKunde` int(10) NULL,
          `kRechnungsadresse` int(10) NULL,
          `kLieferadresse` int(10) NULL,
          `enderecoamsts` int NOT NULL COMMENT 'Timestamp der Prüfung',
          `enderecoamsstatus` text NOT NULL COMMENT 'Statuscodes der Prüfung',
          `enderecoamspredictions` text NOT NULL COMMENT 'Korrekturvorschläge, falls vorhanden',
          `last_change_at` timestamp NOT NULL DEFAULT NOW()
        );");

        $this->execute("ALTER TABLE `xplugin_endereco_jtl5_client_tams` ADD UNIQUE `kKunde` (`kKunde`);");
        $this->execute("ALTER TABLE `xplugin_endereco_jtl5_client_tams` ADD UNIQUE `kRechnungsadresse` (`kRechnungsadresse`);");
        $this->execute("ALTER TABLE `xplugin_endereco_jtl5_client_tams` ADD UNIQUE `kLieferadresse` (`kLieferadresse`);");
    }

    public function down()
    {
        $this->execute("DROP TABLE IF EXISTS `xplugin_endereco_jtl5_client_tams`");
    }
}
