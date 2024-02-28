<?php

namespace Plugin\endereco_jtl5_client\src\Handler;

use JTL\Checkout\DeliveryAddressTemplate;
use JTL\Checkout\Lieferadresse;
use JTL\Customer\Customer;
use JTL\Customer\DataHistory;
use JTL\Helpers\Text;
use JTL\DB\NiceDB;
use Plugin\endereco_jtl5_client\src\Helper\EnderecoService;

class SessionHandler
{
    private EnderecoService $enderecoService;
    private bool $operationProcessed = false;

    /**
     * Constructs the AjaxHandler object.
     *
     * @param EnderecoService $enderecoService The database connection instance.
     */
    public function __construct(
        EnderecoService $enderecoService
    ) {
        $this->enderecoService = $enderecoService;
    }

    /**
     * Closes sessions by performing accounting operations.
     *
     * This method is responsible for closing active sessions. It checks if the request method is POST and
     * ensures the operation is only processed once per request. When called, it delegates to the
     * enderecoService to find current sessions and performs accounting on them.
     *
     * @param array<mixed,mixed> $args An array of arguments, though not explicitly used in the method.
     */
    public function closeSessions(array $args): void
    {
        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            // Sometimes this method is called multiple time within one request, so we prevent multiple execution.
            if ($this->operationProcessed) {
                return;
            }

            $this->enderecoService->doAccountings(
                $this->enderecoService->findSessions()
            );

            $this->operationProcessed = true;
        }
    }
}
