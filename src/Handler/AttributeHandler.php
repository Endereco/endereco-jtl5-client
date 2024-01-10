<?php

namespace Plugin\endereco_jtl5_client\src\Handler;

use JTL\Checkout\DeliveryAddressTemplate;
use JTL\Customer\Customer;
use JTL\DB\NiceDB;
use JTL\Checkout\Bestellung;
use Plugin\endereco_jtl5_client\src\Helper\EnderecoService;

class AttributeHandler
{
    private NiceDB $dbConnection;
    private EnderecoService $enderecoService;

    /**
     * Constructor for the AttributeHandler class.
     *
     * Initializes the database connection used by the handler.
     *
     * @param NiceDB $dbConnection The database connection instance.
     * @param EnderecoService $enderecoService The service for handling Endereco operations.
     */
    public function __construct(
        NiceDB $dbConnection,
        EnderecoService $enderecoService
    ) {
        $this->dbConnection = $dbConnection;
        $this->enderecoService = $enderecoService;
    }

    /**
     * Updates the address in the database.
     *
     * @param array $args The address object to be updated.
     *
     * @return void
     */
    public function saveOrderAttribute(array $args): void
    {
        /** @var Bestellung $order */
        $order = $args['oBestellung'];

        $addressMeta = $this->enderecoService->getOrderAddressMeta($order);

        $this->saveAddressAttributesInDB(
            $order,
            $addressMeta->enderecoamsstatus,
            $addressMeta->enderecoamspredictions,
            $addressMeta->enderecoamsts
        );
    }

    /**
     * Inserts or updates address attributes in the database.
     *
     * Handles the database operation for saving address related attributes like status,
     * predictions, and timestamp for a given order.
     *
     * @param Bestellung $order The order object.
     * @param string $statuses The status information of the address.
     * @param string $predictions The prediction information of the address.
     * @param string $timestamp The timestamp related to the address information.
     */
    public function saveAddressAttributesInDB(
        Bestellung $order,
        string $statuses,
        string $predictions,
        string $timestamp
    ): void {
        if (!empty($statuses)) {
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
                        ':value1' => $timestamp,
                        ':id2' => $order->kBestellung,
                        ':name2' => 'enderecoamsstatus',
                        ':value2' => $statuses,
                        ':id3' => $order->kBestellung,
                        ':name3' => 'enderecoamspredictions',
                        ':value3' => $predictions,
                    ],
                    1
                );
            } catch (\Exception $e) {
                // TODO: log it.
            }
        }
    }
}
