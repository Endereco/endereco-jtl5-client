<?php

namespace Plugin\endereco_jtl5_client\src\Handler;

use JTL\Checkout\DeliveryAddressTemplate;
use JTL\Checkout\Lieferadresse;
use JTL\Customer\Customer;
use JTL\DB\NiceDB;
use JTL\Plugin\PluginInterface;
use Plugin\endereco_jtl5_client\src\Helper\EnderecoService;
use JTL\Smarty\JTLSmarty;

class MetaHandler
{
    private NiceDB $dbConnection;
    private EnderecoService $enderecoService;
    private PluginInterface $plugin;
    private JTLSmarty $smarty;

    private bool $operationProcessed = false;

    /**
     * Constructs the MetaHandler object.
     *
     * Initializes the object with necessary dependencies for its operations.
     *
     * @param PluginInterface $plugin The instance of the plugin.
     * @param NiceDB $dbConnection The database connection instance.
     * @param EnderecoService $enderecoService The service for endereco-related functionalities.
     */
    public function __construct(
        PluginInterface $plugin,
        NiceDB $dbConnection,
        EnderecoService $enderecoService,
        JTLSmarty $smarty
    ) {
        $this->plugin = $plugin;
        $this->dbConnection = $dbConnection;
        $this->enderecoService = $enderecoService;
        $this->smarty = $smarty;
    }

    /**
     * Clears metadata from the session.
     *
     * This method unsets the billing and shipping address metadata from the session,
     * typically invoked on a GET request.
     *
     * @param array $args Contextual arguments, not used in the current implementation.
     */
    public function clearMetaFromSession(array $args): void
    {
        if ('GET' === $_SERVER['REQUEST_METHOD']) {
            unset($_SESSION['EnderecoBillingAddressMeta']);
            unset($_SESSION['EnderecoShippingAddressMeta']);
        }
    }

    /**
     * Clears metadata and cache related to addresses from the session.
     *
     * This method specifically removes the session variables used to store billing address metadata,
     * shipping address metadata, and request cache related to address checks. It is typically used
     * to reset the session state regarding address information, for instance, after a user logs out,
     * or when starting a new checkout process.
     *
     * @param array $args An array of arguments for the method. Currently, this parameter is not used
     *                    within the function, but is included to allow for future extensibility without
     *                    changing the method signature.
     * @return void This method does not return any value.
     */
    public function clearMetaAndCacheFromSession(array $args): void
    {
        unset($_SESSION['EnderecoBillingAddressMeta']);
        unset($_SESSION['EnderecoShippingAddressMeta']);
        unset($_SESSION['EnderecoRequestCache']);
    }

    /**
     * Determines if the PayPal Express Checkout check is active based on the plugin configuration.
     *
     * @return bool Returns true if the PayPal Express Checkout check is enabled in the plugin settings,
     *              false otherwise.
     */
    private function isPayPalExpressCheckoutCheckActive(): bool
    {
        $config = $this->plugin->getConfig();
        return 'on' === $config->getOption('endereco_jtl5_client_check_paypal_express')->value;
    }

    /**
     * Determines if the existing customer check is active based on the plugin configuration.
     *
     * @return bool Returns true if the existing customer check is enabled in the plugin settings, false otherwise.
     */
    private function isExistingCustomerCheckActive(): bool
    {
        $config = $this->plugin->getConfig();
        return 'on' === $config->getOption('endereco_jtl5_client_check_existing')->value;
    }

    /**
     * Determines if the current payment method is PayPal Express Checkout.
     *
     * This method checks the session data to see if the selected payment method (Zahlungsart)
     * corresponds to PayPal Express Checkout, indicated by specific substrings in the module ID.
     *
     * @return bool Returns true if the current payment method is PayPal Express Checkout, false otherwise.
     */
    private function isPayPalExpressCheckout(): bool
    {
        $isPayPal = false;

        // Check if the payment method in the session is set to PayPal Checkout
        if (
            isset($_SESSION['Zahlungsart']) &&
            (strpos($_SESSION['Zahlungsart']->cModulId, 'paypalcheckout') !== false)
        ) {
            $isPayPal = true;
        } elseif (
            isset($_SESSION['Zahlungsart']) &&
            (strpos($_SESSION['Zahlungsart']->cModulId, 'paypalexpress') !== false)
        ) {
            $isPayPal = true;
        }

        return $isPayPal;
    }

    /**
     * Checks if the billing address metadata is present in the provided POST variables.
     *
     * This method assesses whether metadata specific to billing addresses (e.g., validation status)
     * is available in the POST request data.
     *
     * @param array $postVariables The array of POST variables, typically $_POST, to check for address metadata.
     * @return bool Returns true if billing address metadata is present, false otherwise.
     */
    private function hasBillingAddressMeta(array $postVariables): bool
    {
        // Check for the presence of the 'enderecoamsstatus' key in the POST variables
        // This key represents a part of the billing address metadata
        return !empty($postVariables['enderecoamsstatus']);
    }

    /**
     * Checks if the shipping address metadata is present in the provided POST variables.
     *
     * This method assesses whether metadata specific to shipping addresses (e.g., validation status)
     * is available in the POST request data.
     *
     * @param array $postVariables The array of POST variables, typically $_POST, to check for address metadata.
     * @return bool Returns true if shipping address metadata is present, false otherwise.
     */
    private function hasShippingAddressMeta(array $postVariables): bool
    {
        // Check for the presence of the 'enderecodeliveryamsstatus' key in the POST variables
        // This key represents a part of the shipping address metadata
        return !empty($postVariables['enderecodeliveryamsstatus']);
    }

    /**
     * Saves metadata submitted from a form into the database.
     *
     * This method handles different scenarios where address metadata is submitted via POST request,
     * such as editing billing or delivery addresses. It updates the database with the new metadata.
     *
     * @param array $args Contextual arguments that may affect how metadata is saved, like page type or customer ID.
     */
    public function saveMetaFromSubmitInDatabase(array $args): void
    {
        // Return early if the request method is not POST
        if ('POST' !== $_SERVER['REQUEST_METHOD']) {
            return;
        }

        if ($this->operationProcessed) {
            return;
        }

        // Handling the scenario when saving the billing address in "My account"
        if (!empty($_GET['editRechnungsadresse']) && $this->hasBillingAddressMeta($_POST)) {
            $customer = $_SESSION['Kunde'] ?? null;
            // Update the billing address metadata if the customer exists
            if (!empty($customer) && !empty($customer->kKunde)) {
                $this->enderecoService->updateAddressMetaInDB(
                    $customer,
                    $_POST['enderecoamsts'],
                    $_POST['enderecoamsstatus'],
                    $_POST['enderecoamspredictions']
                );
            }

            $this->operationProcessed = true;
        }

        // Handling the scenario when saving the billing address during the checkout process
        if (\PAGE_BESTELLVORGANG === $args['pageType'] && $this->hasBillingAddressMeta($_POST)) {
            $customer = $_SESSION['Kunde'] ?? null;
            // Update the billing address metadata if the customer exists
            if (!empty($customer) && !empty($customer->kKunde)) {
                $this->enderecoService->updateAddressMetaInDB(
                    $customer,
                    $_POST['enderecoamsts'],
                    $_POST['enderecoamsstatus'],
                    $_POST['enderecoamspredictions']
                );
            }

            $this->operationProcessed = true;
        }

        // Handling the scenario when a specific customer ID is provided
        if (!empty($args['customerID']) && $this->hasBillingAddressMeta($_POST)) {
            $customer = new Customer($args['customerID']);
            // Update the billing address metadata for the specified customer
            $this->enderecoService->updateAddressMetaInDB(
                $customer,
                $_POST['enderecoamsts'],
                $_POST['enderecoamsstatus'],
                $_POST['enderecoamspredictions']
            );

            $this->operationProcessed = true;
        }

        // Handling the scenario when editing a specific delivery address
        if (!empty($_GET['editLieferadresse']) && !empty($_GET['editAddress'])) {
            $deliveryAddress = new DeliveryAddressTemplate($this->dbConnection, $_GET['editAddress']);
            // Update the delivery address metadata
            $this->enderecoService->updateAddressMetaInDB(
                $deliveryAddress,
                $_POST['enderecodeliveryamsts'],
                $_POST['enderecodeliveryamsstatus'],
                $_POST['enderecodeliveryamspredictions']
            );

            $this->operationProcessed = true;
        }
    }

    /**
     * Saves metadata submitted from a form into the database.
     *
     * This method handles different scenarios where address metadata is submitted via POST request,
     * such as editing billing or delivery addresses. It updates the database with the new metadata.
     *
     * @param array $args Contextual arguments that may affect how metadata is saved, like page type or customer ID.
     */
    public function saveMetaFromSubmitInCache(array $args): void
    {

        // Return early if the request method is not POST
        if ('POST' !== $_SERVER['REQUEST_METHOD']) {
            return;
        }

        // Handling the scenario when saving the billing address during the checkout process
        if (\PAGE_BESTELLVORGANG === $args['pageType'] && $this->hasBillingAddressMeta($_POST)) {
            // Update the billing address metadata in session variable
            $address = $this->extractBillingAddressFromPost($_POST);
            $this->enderecoService->updateAddressMetaInCache(
                $address,
                $_POST['enderecoamsstatus'],
                $_POST['enderecoamspredictions']
            );
        }

        if (\PAGE_BESTELLVORGANG === $args['pageType'] && $this->hasShippingAddressMeta($_POST)) {
            // Update the shipping address metadata in session variable
            $address = $this->extractShippingAddressFromPost($_POST);
            $this->enderecoService->updateAddressMetaInCache(
                $address,
                $_POST['enderecodeliveryamsstatus'],
                $_POST['enderecodeliveryamspredictions']
            );
        }
    }

    /**
     * Extracts billing address information from a post request variable.
     *
     * This method parses a given post request variable array to extract billing address
     * details, including country code, postal code, locality (city), street name,
     * building number, and any additional address information if available. It expects
     * each piece of address information to be provided under specific keys.
     *
     * @param array $postVariable The post request variable containing billing address information.
     *                            Expected keys are 'land' (country code), 'plz' (postal code),
     *                            'ort' (locality), 'strasse' (street name), 'hausnummer' (building number),
     *                            and 'adresszusatz' (additional info), which is optional.
     * @return array An associative array containing the extracted billing address, formatted
     *               with keys as 'countryCode', 'postalCode', 'locality', 'streetName',
     *               'buildingNumber', and 'additionalInfo'.
     */
    public function extractBillingAddressFromPost($postVariable)
    {
        $address = [
            'countryCode' => strtoupper($postVariable['land']),
            'postalCode' => $postVariable['plz'],
            'locality' => $postVariable['ort'],
            'streetName' => $postVariable['strasse'],
            'buildingNumber' => $postVariable['hausnummer'],
            'additionalInfo' => $postVariable['adresszusatz'] ?? ''
        ];

        return $address;
    }

    /**
     * Extracts shipping address information from a nested structure within a post request variable.
     *
     * Similar to the billing address extraction, this method focuses on extracting shipping
     * address details from a nested array structure within the given post request variable.
     * It requires the shipping address to be located under 'register' and then 'shipping_address',
     * with specific keys for each piece of address information.
     *
     * @param array $postVariable The post request variable containing nested shipping address
     *                            information. Expected structure is $postVariable['register']['shipping_address']
     *                            with keys 'land' (country code), 'plz' (postal code), 'ort' (locality),
     *                            'strasse' (street name), 'hausnummer' (building number), and
     *                            'adresszusatz' (additional info), which is optional.
     * @return array An associative array containing the extracted shipping address, with keys
     *               as 'countryCode', 'postalCode', 'locality', 'streetName', 'buildingNumber',
     *               and 'additionalInfo'.
     */
    public function extractShippingAddressFromPost($postVariable)
    {
        $address = [
            'countryCode' => strtoupper($postVariable['register']['shipping_address']['land']),
            'postalCode' => $postVariable['register']['shipping_address']['plz'],
            'locality' => $postVariable['register']['shipping_address']['ort'],
            'streetName' => $postVariable['register']['shipping_address']['strasse'],
            'buildingNumber' => $postVariable['register']['shipping_address']['hausnummer'],
            'additionalInfo' => $postVariable['register']['shipping_address']['adresszusatz'] ?? ''
        ];

        return $address;
    }

    /**
     * Processes the address check for a given address object.
     *
     * This method checks the address using the enderecoService, applies any automatic corrections,
     * and updates the address in the session. It supports different types of address objects.
     *
     * @param mixed &$address The address object to be checked and updated.
     *
     * @return \stdClass The address metadata after processing.
     */
    private function processAddressCheck(&$address): \stdClass
    {
        if (
            !$address instanceof Lieferadresse
            && !$address instanceof DeliveryAddressTemplate
            && !$address instanceof Customer
        ) {
            throw new InvalidArgumentException(
                'Address must be of type Lieferadresse, DeliveryAddressTemplate, or Customer'
            );
        }

        // Perform an address check using the endereco service
        $checkResult = $this->enderecoService->checkAddress($address);

        // Apply automatic corrections if necessary and update the address
        if ($checkResult->isAutomaticCorrection() && !$checkResult->isConfirmedByCustomer()) {
            $addressMeta = $checkResult->getMetaAfterAutomaticCorrection();
            $address = $this->enderecoService->applyAutocorrection($address, $checkResult);
            $this->enderecoService->updateAddressInSession($address);
        } else {
            $addressMeta = $checkResult->getMeta();
        }

        return $addressMeta;
    }

    /**
     * Attempts to retrieve metadata from a cached result of a previous address check.
     *
     * This method is designed to check if there is a cached result for an address check performed
     * earlier, and if so, to retrieve the metadata associated with that result. It accepts an
     * address object, which could be an instance of Lieferadresse, DeliveryAddressTemplate, or
     * Customer. The method leverages the `lookupInCache` method of the `enderecoService` to perform
     * this operation.
     *
     * If a cached result is found, the method extracts and returns the metadata part of the address
     * check result. The metadata typically includes information about the validation process, such as
     * validation status, suggestions for corrections, or any warnings or errors encountered during the
     * address check.
     *
     * @param mixed $address The address object for which the metadata is to be retrieved.
     *
     * @return \stdClass An object containing the metadata from the cached address check result.
     */
    private function tryToLoadMetaFromCache($address): \stdClass
    {
        if (
            !$address instanceof Lieferadresse
            && !$address instanceof DeliveryAddressTemplate
            && !$address instanceof Customer
        ) {
            throw new InvalidArgumentException(
                'Address must be of type Lieferadresse, DeliveryAddressTemplate, or Customer'
            );
        }
        // Perform an address check using the endereco service
        $checkResult = $this->enderecoService->lookupInCache($address);
        $addressMeta = $checkResult->getMeta();
        return $addressMeta;
    }

    /**
     * Loads billing address metadata into the session for a given customer.
     *
     * This method queries for existing metadata and updates it based on the customer's current billing
     * address. It handles different scenarios, such as PayPal Express Checkout, and updates both the
     * database and session with the new metadata.
     *
     * @param Customer $customer The customer whose billing address metadata needs to be updated.
     */
    private function loadBillingAddressMetaToSession(Customer $customer): void
    {
        $addressMeta = null;

        // Query for existing metadata in the database
        if (!empty($customer->kKunde)) {
            $addressMeta = $this->dbConnection->queryPrepared(
                "SELECT `xplugin_endereco_jtl5_client_tams`.*
             FROM `xplugin_endereco_jtl5_client_tams`
             WHERE `kKunde` = :id",
                [':id' => $customer->kKunde],
                1
            );

            $isEmptyStatus = empty($addressMeta) || empty($addressMeta->enderecoamsstatus);
            if ($isEmptyStatus) {
                // Try to load from cache
                $addressMeta = $this->tryToLoadMetaFromCache($customer);
            }

            $isEmptyStatus = empty($addressMeta) || empty($addressMeta->enderecoamsstatus);

            // If no metadata is found and an existing customer check is active, process the address check
            if ($isEmptyStatus && $this->isExistingCustomerCheckActive()) {
                $addressMeta = $this->processAddressCheck($customer);

                // Update address metadata in the database
                $this->enderecoService->updateAddressMetaInDB(
                    $customer,
                    $addressMeta->enderecoamsts,
                    $addressMeta->enderecoamsstatus,
                    $addressMeta->enderecoamspredictions
                );
            }
        } elseif ($this->isPayPalExpressCheckout() && $this->isPayPalExpressCheckoutCheckActive()) {
            // Process address check for PayPal Express Checkout
            $addressMeta = $this->processAddressCheck($customer);
        } elseif ($this->isExistingCustomerCheckActive()) {
            // Try to load from cache
            $addressMeta = $this->tryToLoadMetaFromCache($customer);
            $isEmptyStatus = empty($addressMeta) || empty($addressMeta->enderecoamsstatus);
            if ($isEmptyStatus) {
                // Process address check for a guest customer
                $addressMeta = $this->processAddressCheck($customer);
            }
        } else {
            // Try to load from cache
            $addressMeta = $this->tryToLoadMetaFromCache($customer);
        }

        // Update the address metadata in the session
        if (!empty($addressMeta)) {
            $this->enderecoService->updateAddressMetaInSession(
                $addressMeta->enderecoamsts,
                $addressMeta->enderecoamsstatus,
                $addressMeta->enderecoamspredictions,
                'EnderecoBillingAddressMeta'
            );
        }
    }

    /**
     * Loads shipping address metadata into the session for a given delivery address.
     *
     * This method handles different scenarios such as existing customer checks and PayPal Express Checkout.
     * It updates the database and session with the shipping address metadata.
     *
     * @param mixed $deliveryAddress The delivery address whose metadata needs to be updated.
     */
    private function loadShippingAddressMetaToSession($deliveryAddress): void
    {
        if (!$deliveryAddress instanceof Lieferadresse && !$deliveryAddress instanceof DeliveryAddressTemplate) {
            throw new InvalidArgumentException('Address must be of type Lieferadresse or DeliveryAddressTemplate');
        }

        $addressMeta = null;

        // Query for existing metadata in the database
        if (!empty($deliveryAddress->kLieferadresse)) {
            $addressMeta = $this->dbConnection->queryPrepared(
                "SELECT `xplugin_endereco_jtl5_client_tams`.*
             FROM `xplugin_endereco_jtl5_client_tams`
             WHERE `kLieferadresse` = :id",
                [':id' => $deliveryAddress->kLieferadresse],
                1
            );

            $isEmptyStatus = empty($addressMeta) || empty($addressMeta->enderecoamsstatus);
            if ($isEmptyStatus) {
                // Try to load from cache
                $addressMeta = $this->tryToLoadMetaFromCache($customer);
            }

            $isEmptyStatus = empty($addressMeta) || empty($addressMeta->enderecoamsstatus);
            // If no metadata is found and an existing customer check is active, process the address check
            if ($isEmptyStatus && $this->isExistingCustomerCheckActive()) {
                $addressMeta = $this->processAddressCheck($deliveryAddress);

                // Update address metadata in the database
                $this->enderecoService->updateAddressMetaInDB(
                    $deliveryAddress,
                    $addressMeta->enderecoamsts,
                    $addressMeta->enderecoamsstatus,
                    $addressMeta->enderecoamspredictions
                );
            }
        } elseif (
            $this->isPayPalExpressCheckout() &&
            $this->isPayPalExpressCheckoutCheckActive() &&
            $this->enderecoService->isBillingDifferentFromShipping()
        ) {
            // Process address check for PayPal Express Checkout with different billing and shipping addresses
            $addressMeta = $this->processAddressCheck($deliveryAddress);
        } elseif ($this->isExistingCustomerCheckActive() && $this->enderecoService->isBillingDifferentFromShipping()) {
            // Try to load from cache
            $addressMeta = $this->tryToLoadMetaFromCache($deliveryAddress);
            $isEmptyStatus = empty($addressMeta) || empty($addressMeta->enderecoamsstatus);
            if ($isEmptyStatus) {
                // Process address check for a situation where billing and shipping addresses are different
                $addressMeta = $this->processAddressCheck($deliveryAddress);
            }
        } else {
            // Try to load from cache
            $addressMeta = $this->tryToLoadMetaFromCache($deliveryAddress);
        }

        // Update the address metadata in the session
        if (!empty($addressMeta)) {
            $this->enderecoService->updateAddressMetaInSession(
                $addressMeta->enderecoamsts,
                $addressMeta->enderecoamsstatus,
                $addressMeta->enderecoamspredictions,
                'EnderecoShippingAddressMeta'
            );
        }
    }

    /**
     * Loads metadata from the database based on the current context within the user interface.
     *
     * This method handles different scenarios such as editing billing or delivery addresses in the user's account,
     * or processing addresses during the checkout. It updates the session with the relevant metadata from the database.
     *
     * @param array $args Contextual arguments that may affect how metadata is loaded.
     */
    public function loadMetaFromDatabase(array $args): void
    {
        // Clear any existing metadata from the session. But only once.
        if (!$this->operationProcessed) {
            $this->enderecoService->clearMetaFromSession();
            $this->operationProcessed = true;
        }

        // Handling the scenario when editing the billing address in "My account" (optionally the shipping too)
        if (!empty($_GET['editRechnungsadresse'])) {
            // Load billing address metadata for the current customer
            $customer = $_SESSION['Kunde'];
            if ($customer) {
                $this->loadBillingAddressMetaToSession($customer);
            }

            // If a delivery address is set in the session, load its metadata as well
            if (
                isset($_SESSION['Lieferadresse']) &&
                empty($_SESSION['Lieferadresse']->kLieferadresse) &&
                empty($_SESSION['shippingAddressPresetID']) &&
                empty($this->smarty->getTemplateVars('shippingAddressPresetID'))
            ) {
                $deliveryAddress = $_SESSION['Lieferadresse'];
                $this->loadShippingAddressMetaToSession($deliveryAddress);
            }
        }

        // Handling the case of being in checkout after clicking on "edit delivery address" in payment page.
        if (!empty($_GET['editLieferadresse']) && (\HOOK_BESTELLVORGANG_PAGE === (int) $args[0])) {
            $customer = $_SESSION['Kunde'];
            $this->loadBillingAddressMetaToSession($customer);

            $deliveryAddress = $_SESSION['Lieferadresse'];
            $this->loadShippingAddressMetaToSession($deliveryAddress);
        }

        // Handling the scenario when editing a specific delivery address in "My account"
        if (!empty($_GET['editLieferadresse']) && !empty($_GET['editAddress'])) {
            // Load metadata for the specified delivery address
            $deliveryAddressId = intval($_GET['editAddress']);
            $deliveryAddress = new DeliveryAddressTemplate($this->dbConnection, $deliveryAddressId);
            $this->loadShippingAddressMetaToSession($deliveryAddress);
        }

        // Handling the scenario in "Checkout" on the last page.
        if (\HOOK_BESTELLVORGANG_PAGE_STEPBESTAETIGUNG === (int) $args[0]) {
            // Load billing address metadata for the current customer
            $this->loadBillingAddressMetaToSession($_SESSION['Kunde']);

            // Load metadata for either a preset shipping address or the default one in the session
            if (!empty($_SESSION['shippingAddressPresetID'])) {
                $deliveryAddress = new DeliveryAddressTemplate(
                    $this->dbConnection,
                    $_SESSION['shippingAddressPresetID']
                );
                $this->loadShippingAddressMetaToSession($deliveryAddress);
            } else {
                $deliveryAddress = $_SESSION['Lieferadresse'];
                $this->loadShippingAddressMetaToSession($deliveryAddress);
            }
        }
    }
}
