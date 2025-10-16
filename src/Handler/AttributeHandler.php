<?php

namespace Plugin\endereco_jtl5_client\src\Handler;

use JTL\Customer\Customer;
use JTL\DB\NiceDB;
use JTL\Checkout\Bestellung;
use JTL\Plugin\PluginInterface;
use Plugin\endereco_jtl5_client\src\Helper\EnderecoService;
use JTL\DB\DbInterface;
use Plugin\endereco_jtl5_client\src\Structures\AddressMeta;

class AttributeHandler
{
    private DbInterface $dbConnection;
    private EnderecoService $enderecoService;
    private PluginInterface $plugin;

    /**
     * Constructor for the AttributeHandler class.
     *
     * Initializes the database connection used by the handler.
     *
     * @param DbInterface $dbConnection The database connection instance.
     * @param EnderecoService $enderecoService The service for handling Endereco operations.
     * @param PluginInterface $plugin The instance of the plugin.
     */
    public function __construct(
        DbInterface $dbConnection,
        EnderecoService $enderecoService,
        PluginInterface $plugin
    ) {
        $this->dbConnection = $dbConnection;
        $this->enderecoService = $enderecoService;
        $this->plugin = $plugin;
    }

    /**
     * Updates the address in the database.
     *
     * @param array<string,mixed> $args The address object to be updated.
     *
     * @return void
     */
    public function saveOrderAttribute(array $args): void
    {
        if (!$this->isSaveAttributesToDbActive()) {
            return;
        }

        /** @var Bestellung $order */
        $order = $args['oBestellung'];

        $addressMeta = $this->enderecoService->getOrderAddressMeta();

        $this->saveAddressAttributesInDB(
            $order,
            $addressMeta
        );
    }

    /**
     * Inserts or updates address attributes in the database.
     *
     * Handles the database operation for saving address related attributes like status,
     * predictions, and timestamp for a given order.
     *
     * @param Bestellung $order The order object.
     * @param AddressMeta $addressMeta Metainformation of the address.
     */
    public function saveAddressAttributesInDB(
        Bestellung $order,
        AddressMeta $addressMeta
    ): void {
        if ($addressMeta->hasAnyStatus()) {
            try {
                $this->dbConnection->queryPrepared(
                    "INSERT INTO `tbestellattribut` 
                    (`kBestellung`, `cName`, `cValue`)
                 VALUES 
                    (:id1, :name1, :value1),
                    (:id2, :name2, :value2),
                    (:id3, :name3, :value3) 
                ON DUPLICATE KEY UPDATE    
                   `cValue`=VALUES(`cValue`)
                ",
                    [
                        ':id1' => $order->kBestellung,
                        ':name1' => 'enderecoamsts',
                        ':value1' => $addressMeta->getTimestamp(),
                        ':id2' => $order->kBestellung,
                        ':name2' => 'enderecoamsstatus',
                        ':value2' => $addressMeta->getStatusAsString(),
                        ':id3' => $order->kBestellung,
                        ':name3' => 'enderecoamspredictions',
                        ':value3' => $addressMeta->getPredictionsAsString()
                    ],
                    1
                );
            } catch (\Exception $e) {
                // TODO: log it.
            }
        }
    }

    /**
     * Determines if the save attributes to database option is active based on the
     * plugin configuration.
     *
     * @return bool Returns true if the save attributes to database option is enabled
     * in the plugin settings, false otherwise.
     */
    private function isSaveAttributesToDbActive(): bool
    {
        $config = $this->plugin->getConfig();
        $option = $config->getOption('endereco_jtl5_client_ams_to_db');
        return $option !== null && ('on' === $option->value);
    }
}
