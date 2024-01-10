<?php

namespace Plugin\endereco_jtl5_client\src\Handler;

use InvalidArgumentException;
use JTL\phpQuery\phpQuery;
use JTL\Plugin\PluginInterface;
use JTL\Shop;
use JTL\phpQuery\phpQueryObject;
use Plugin\endereco_jtl5_client\src\Helper\EnderecoService;
use JTL\Services\JTL\CryptoService;
use JTL\Smarty\JTLSmarty;
use JTL\Template\TemplateService;
use JTL\DB\NiceDB;

class TemplateHandler
{
    private PluginInterface $plugin;
    private EnderecoService $enderecoService;
    private CryptoService $cryptoService;
    private TemplateService $templateService;
    private NiceDB $dbConnection;

    private const TEMPLATE_BILLING_FORM_META = __DIR__ . '/../../smarty_templates/billing_ams_initiation.tpl';
    private const TEMPLATE_SHIPPING_FORM_META = __DIR__ . '/../../smarty_templates/shipping_ams_initiation.tpl';
    private const TEMPLATE_CHECKOUT_FAKE_BILLING_FORM = __DIR__ . '/../../smarty_templates/fake_billing_address.tpl';
    private const TEMPLATE_CHECKOUT_FAKE_SHIPPING_FORM = __DIR__ . '/../../smarty_templates/fake_shipping_address.tpl';
    private const TEMPLATE_CONFIG = __DIR__ . '/../../smarty_templates/config.tpl';
    private const TEMPLATE_JS_BUNDLE = __DIR__ . '/../../smarty_templates/load_js.tpl';

    public function __construct(
        PluginInterface $plugin,
        EnderecoService $enderecoService,
        CryptoService $cryptoService,
        TemplateService $templateService,
        NiceDB $dbConnection
    ) {
        $this->plugin = $plugin;
        $this->enderecoService = $enderecoService;
        $this->cryptoService = $cryptoService;
        $this->templateService = $templateService;
        $this->dbConnection = $dbConnection;
    }

    /**
     * Adds an error container (div element) after the parent of the specified element in the document.
     *
     * This method finds the element in the document using the provided CSS selector and inserts
     * a new div element right after its parent. The div is created with attributes specified in
     * the `$containerAttributes` array. If the element specified by the selector is not found,
     * the method does nothing. If the attributes provided are not in the correct format (both key
     * and value as strings), an InvalidArgumentException is thrown.
     *
     * @param phpQueryObject $document The phpQuery object representing the DOM document.
     * @param string $selector The CSS selector used to find the target element in the document.
     * @param array $containerAttributes An associative array of attributes for the error container.
     *                                  The array should contain key-value pairs where the key is
     *                                  the attribute name and the value is the attribute value.
     *                                  Both keys and values should be strings.
     *
     * @return void
     *
     * @throws InvalidArgumentException if the attribute format is invalid.
     *
     * @example
     * ```php
     * $this->_addErrorContainer(
     *     $document,
     *     '#email[type="email"]',
     *     ['id' => 'container-email-error-messages']
     * );
     * ```
     *
     * @note This method assumes that the selected element has a parent. If the element is at the
     *       root of the document, the method may not behave as expected.
     */
    private function addErrorMessageContainer(
        phpQueryObject $document,
        string $selector,
        array $containerAttributes
    ): void {
        $attrString = "";
        foreach ($containerAttributes as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                throw new InvalidArgumentException("Invalid attribute format.");
            }
            $attrString .= htmlspecialchars($key) . '="' . htmlspecialchars($value) . '" ';
        }

        // Check if the selector exists in the document
        $elements = $document->find($selector);
        if ($elements->length === 0) {
            return; // Exit if the selector is not found
        }

        // If the selector is found, proceed with DOM manipulation
        $elements->parent()->after("<div $attrString></div>");
    }

    /**
     * Checks if a billing address is present in the given HTML document.
     *
     * This method evaluates the existence of specific form fields related to a billing
     * address in the provided HTML document. It uses CSS selectors to search for elements
     * with specific name attributes ('land', 'plz', 'ort', 'strasse', 'hausnummer') and
     * checks if these elements are present in the document.
     *
     * @param phpQueryObject $document The HTML document to be searched for billing address fields.
     *
     * @return bool Returns true if all required billing address fields are found, false otherwise.
     */
    private function hasBillingAddress(phpQueryObject $document): bool
    {
        return $document->find('[name="land"]')->length
            && $document->find('[name="plz"]')->length
            && $document->find('[name="ort"]')->length
            && $document->find('[name="strasse"]')->length
            && $document->find('[name="hausnummer"]')->length;
    }

    /**
     * Checks if a shipping address is present in the given HTML document.
     *
     * This method determines the presence of specific form fields related to a shipping
     * address in the provided HTML document. It searches for elements with particular name
     * attributes (encapsulating 'land', 'plz', 'ort', 'strasse', 'hausnummer' within the
     * 'register[shipping_address]' scope) and verifies if these elements exist in the document.
     *
     * @param phpQueryObject $document The HTML document to be searched for shipping address fields.
     *
     * @return bool Returns true if all required shipping address fields are found, false otherwise.
     */
    private function hasShippingAddress(phpQueryObject $document): bool
    {
        return $document->find('[name="register[shipping_address][land]"]')->length
            && $document->find('[name="register[shipping_address][plz]"]')->length
            && $document->find('[name="register[shipping_address][ort]"]')->length
            && $document->find('[name="register[shipping_address][strasse]"]')->length
            && $document->find('[name="register[shipping_address][hausnummer]"]')->length;
    }

    /**
     * Determines if either a billing or shipping address is present in the given HTML document.
     *
     * This method checks the provided HTML document for the presence of billing and shipping
     * address fields. It utilizes two internal methods: `hasBillingAddress` and `hasShippingAddress`,
     * to perform these checks separately. The function returns true if either a billing address
     * or a shipping address is found in the document.
     *
     * @param phpQueryObject $document The HTML document to be searched for address fields.
     *
     * @return bool Returns true if either billing or shipping address fields are found, false otherwise.
     */
    private function hasAnyAddress(phpQueryObject $document): bool
    {
        return $this->hasBillingAddress($document) || $this->hasShippingAddress($document);
    }

    /**
     * Checks if the given HTML document represents a confirmation page.
     *
     * This method assesses whether the provided HTML document contains specific elements
     * that are indicative of a confirmation page. It iterates over an array of CSS selectors
     * corresponding to these elements (defined in `$markerElementSelectors`). If any of these
     * elements are found within the document, the method concludes that the page is a confirmation
     * page and returns true.
     *
     * @param phpQueryObject $document The HTML document to be examined.
     *
     * @return bool Returns true if the document is identified as a confirmation page, false otherwise.
     */
    private function isConfirmationPage(phpQueryObject $document): bool
    {
        $markerElementSelectors = [
            '#order-confirm',
        ];
        $anyFound = false;
        foreach ($markerElementSelectors as $markerElementSelector) {
            $anyFound = $anyFound || ((bool) $document->find($markerElementSelector)->length);
        }

        return $anyFound;
    }

    /**
     * Adds billing address metadata to the address form in the provided HTML document.
     *
     * This method checks if the HTML document contains a billing address form. If found, it
     * assigns certain metadata (timestamp, status, and serialized predictions) related to the
     * billing address to the Smarty template engine. The method then fetches the HTML content
     * for these metadata from a specified template and inserts it after the 'strasse' field in
     * the billing address form.
     *
     * @param phpQueryObject $document The HTML document containing the billing address form.
     * @param JTLSmarty $smarty The Smarty template engine instance for rendering the metadata.
     * @param string $timestamp (optional) The timestamp metadata, defaults to an empty string.
     * @param string $status (optional) The status metadata, defaults to an empty string.
     * @param string $predictionsSerialized (optional) The serialized predictions, defaults to an empty string.
     */
    private function addBillingAMSMetaToAddressForm(
        phpQueryObject $document,
        JTLSmarty $smarty,
        string $timestamp = '',
        string $status = '',
        string $predictionsSerialized = ''
    ): void {

        if (!$this->hasBillingAddress($document)) {
            return;
        }

        if (empty($predictionsSerialized)) {
            $predictionsSerialized = '[]';
        }

        $smarty->assign('endereco_amsts', $timestamp)
               ->assign('endereco_amsstatus', $status)
               ->assign('endereco_amspredictions', $predictionsSerialized);

        $file = self::TEMPLATE_BILLING_FORM_META;
        $html = $smarty->fetch($file);

        $document->find('[name="strasse"]')->after($html);
    }

    /**
     * Appends shipping address metadata to the address form within the provided HTML document.
     *
     * This method first checks if the HTML document contains a shipping address form. If present,
     * it proceeds to assign metadata (timestamp, status, and serialized predictions) related to the
     * shipping address to the Smarty template engine. The method then fetches the HTML content for
     * these metadata from a specified template and inserts it after the 'strasse' field in the
     * shipping address form. This addition of metadata is useful for enhancing form functionality
     * or providing additional context to users.
     *
     * @param phpQueryObject $document The HTML document containing the shipping address form.
     * @param JTLSmarty $smarty The Smarty template engine instance used for rendering the metadata.
     * @param string $timestamp (optional) The timestamp metadata, defaults to an empty string if not provided.
     * @param string $status (optional) The status metadata, defaults to an empty string if not provided.
     * @param string $predictionsSerialized (optional) The serialized predictions metadata, defaults to '[]' if empty.
     */
    private function addShippingAMSMetaToAddressForm(
        phpQueryObject $document,
        JTLSmarty $smarty,
        string $timestamp = '',
        string $status = '',
        string $predictionsSerialized = ''
    ): void {

        if (!$this->hasShippingAddress($document)) {
            return;
        }

        if (empty($predictionsSerialized)) {
            $predictionsSerialized = '[]';
        }

        $smarty->assign('endereco_delivery_amsts', $timestamp)
            ->assign('endereco_delivery_amsstatus', $status)
            ->assign('endereco_delivery_amspredictions', $predictionsSerialized);

        $file = self::TEMPLATE_SHIPPING_FORM_META;
        $html = $smarty->fetch($file);

        $document->find('[name="register[shipping_address][strasse]"]')->after($html);
    }

    /**
     * Includes the SDK necessary for handling addresses in the provided HTML document.
     *
     * This method integrates the SDK into the HTML document if the document contains any address forms
     * or is a confirmation page. It prepares necessary data such as country mapping, plugin information,
     * and URLs for API calls. The method then assigns these data to the Smarty template engine and fetches
     * the required HTML content to be included in the document's head and body. This content includes
     * configuration settings, localized texts, and JavaScript bundles necessary for the SDK's functionality.
     *
     * @param phpQueryObject $document The HTML document where the SDK is to be included.
     * @param JTLSmarty $smarty The Smarty template engine instance used for rendering HTML content.
     */
    private function includeSDK($document, $smarty): void
    {
        if (
            !$this->hasAnyAddress($document) &&
            !$this->isConfirmationPage($document)
        ) {
            return;
        }

        // Get template name.
        $templateName = $this->templateService->getActiveTemplate()->getDir();

        // Get country mapping.
        $countries = $this->dbConnection->queryPrepared(
            "SELECT * FROM `tland`",
            [],
            2
        );

        $countryMapping = [];
        foreach ($countries as $country) {
            if (!empty($_SESSION['cISOSprache']) && 'ger' === $_SESSION['cISOSprache']) {
                $countryMapping[$country->cISO] = $country->cDeutsch;
            } else {
                $countryMapping[$country->cISO] = $country->cEnglisch;
            }
        }

        $pluginIOPath = URL_SHOP . '/plugins/endereco_jtl5_client/io.php';

        $agentInfo = "Endereco JTL5 Client v" . $this->plugin->getMeta()->getVersion();

        $smarty->assign('endereco_theme_name', strtolower($templateName))
               ->assign('endereco_plugin_config', $this->plugin->getConfig())
               ->assign('endereco_locales', $this->plugin->getLocalization())
               ->assign('endereco_plugin_ver', $this->plugin->getMeta()->getVersion())
               ->assign('endereco_agent_info', $agentInfo)
               ->assign('endereco_api_url', $pluginIOPath)
               ->assign(
                   'endereco_jtl5_client_country_mapping',
                   str_replace('\'', '\\\'', json_encode($countryMapping))
               );

        $html = $smarty->fetch(self::TEMPLATE_CONFIG);
        $document->find('head')->prepend($html);

        $html = $smarty->fetch(self::TEMPLATE_JS_BUNDLE);
        $document->find('body')->append($html);
    }

    /**
     * Adds billing address information to the confirmation page in the provided HTML document.
     *
     * This method is responsible for appending billing address details to the confirmation page.
     * It first checks if the given document is a confirmation page. If true, it sets various billing
     * address attributes (like country code, postal code, locality, etc.) to the Smarty template engine.
     * These attributes are then used to render HTML content, which is prepended to the body of the
     * document. This function is crucial for displaying confirmed billing address details on the
     * confirmation page.
     *
     * @param phpQueryObject $document The HTML document representing the confirmation page.
     * @param JTLSmarty $smarty The Smarty template engine instance for rendering HTML content.
     * @param string $countryCode The country code of the billing address.
     * @param string $postalCode The postal code of the billing address.
     * @param string $locality The locality or city of the billing address.
     * @param string $streetName The street name of the billing address.
     * @param string $buildingNumber The building number of the billing address.
     * @param string $additionalInfo Any additional information for the billing address.
     * @param string $timestamp The timestamp related to the billing address.
     * @param string $status The status of the billing address.
     * @param string $predictionsSerialized Serialized prediction data for the billing address.
     */
    private function addBillingAddressToConfirmationPage(
        phpQueryObject $document,
        JTLSmarty $smarty,
        string $countryCode,
        string $postalCode,
        string $locality,
        string $streetName,
        string $buildingNumber,
        string $additionalInfo,
        string $timestamp,
        string $status,
        string $predictionsSerialized
    ): void {
        if (!$this->isConfirmationPage($document)) {
            return;
        }

        // Set smarty values for billing.
        $smarty->assign('endereco_billing_countrycode', $countryCode)
            ->assign('endereco_billing_postal_code', $postalCode)
            ->assign('endereco_billing_locality', $locality)
            ->assign('endereco_billing_street_name', $streetName)
            ->assign('endereco_billing_building_number', $buildingNumber)
            ->assign('endereco_billing_addinfo', $additionalInfo)
            ->assign('endereco_billing_ts', $timestamp)
            ->assign('endereco_billing_status', $status)
            ->assign('endereco_billing_predictions', $predictionsSerialized)
            ->assign(
                'endereco_shipping_address_is_different',
                $this->enderecoService->isBillingDifferentFromShipping()
            );

        $html = $smarty->fetch(self::TEMPLATE_CHECKOUT_FAKE_BILLING_FORM);

        $document->find('body')->prepend($html);
    }

    /**
     * Appends shipping address information to the confirmation page in the provided HTML document.
     *
     * This method adds shipping address details to the confirmation page of an order. It first verifies
     * that the given document is indeed a confirmation page and that the billing and shipping addresses
     * are different. After these checks, it assigns various shipping address attributes (such as country
     * code, postal code, locality, etc.) to the Smarty template engine. These details are then rendered
     * into HTML content, which is prepended to the body of the document. This addition is essential for
     * displaying the shipping address details on the confirmation page, especially when it differs from
     * the billing address.
     *
     * @param phpQueryObject $document The HTML document representing the confirmation page.
     * @param JTLSmarty $smarty The Smarty template engine instance for rendering HTML content.
     * @param string $countryCode The country code of the shipping address.
     * @param string $postalCode The postal code of the shipping address.
     * @param string $locality The locality or city of the shipping address.
     * @param string $streetName The street name of the shipping address.
     * @param string $buildingNumber The building number of the shipping address.
     * @param string $additionalInfo Any additional information for the shipping address.
     * @param string $timestamp The timestamp related to the shipping address.
     * @param string $status The status of the shipping address.
     * @param string $predictionsSerialized Serialized prediction data for the shipping address.
     */
    private function addShippingAddressToConfirmationPage(
        phpQueryObject $document,
        JTLSmarty $smarty,
        string $countryCode,
        string $postalCode,
        string $locality,
        string $streetName,
        string $buildingNumber,
        string $additionalInfo,
        string $timestamp,
        string $status,
        string $predictionsSerialized
    ): void {
        if (!$this->isConfirmationPage($document)) {
            return;
        }

        if (!$this->enderecoService->isBillingDifferentFromShipping()) {
            return;
        }

        // Set smarty values for billing.
        $smarty->assign('endereco_shipping_countrycode', $countryCode)
            ->assign('endereco_shipping_postal_code', $postalCode)
            ->assign('endereco_shipping_locality', $locality)
            ->assign('endereco_shipping_street_name', $streetName)
            ->assign('endereco_shipping_building_number', $buildingNumber)
            ->assign('endereco_shipping_addinfo', $additionalInfo)
            ->assign('endereco_shipping_ts', $timestamp)
            ->assign('endereco_shipping_status', $status)
            ->assign('endereco_shipping_predictions', $predictionsSerialized);

        $html = $smarty->fetch(self::TEMPLATE_CHECKOUT_FAKE_SHIPPING_FORM);

        $document->find('body')->prepend($html);
    }

    /**
     * Integrates various address management functionalities into the general template.
     *
     * This method orchestrates the integration of multiple functionalities related to address
     * management within the template. It includes adding error message containers for email fields,
     * appending address metadata, and incorporating address details into the confirmation page.
     * The method checks if the enderecoService is ready before proceeding with the integration.
     * It then utilizes session data for various address-related attributes to perform these integrations.
     *
     * @param array $args An associative array containing 'smarty' (the Smarty template engine instance)
     *                    and 'document' (the HTML document to be modified).
     */
    public function generalTemplateIntegration(array $args): void
    {
        // Set variables.
        $smarty = $args['smarty'];
        $document = $args['document'];

        if (!$this->enderecoService->isReady()) {
            return;
        }

        $this->addErrorMessageContainer(
            $document,
            '#email[type="email"]',
            [
                'id' => 'container-email-error-messages'
            ]
        );

        $this->addErrorMessageContainer(
            $document,
            '[name="register[shipping_address][email]"]',
            [
                'id' => 'container-shipping-email-error-messages'
            ]
        );

        $this->addBillingAMSMetaToAddressForm(
            $document,
            $smarty,
            $_SESSION['EnderecoBillingAddressMeta']['enderecoamsts'] ?? '',
            $_SESSION['EnderecoBillingAddressMeta']['enderecoamsstatus'] ?? '',
            $_SESSION['EnderecoBillingAddressMeta']['enderecoamspredictions'] ?? ''
        );

        $this->addShippingAMSMetaToAddressForm(
            $document,
            $smarty,
            $_SESSION['EnderecoShippingAddressMeta']['enderecoamsts'] ?? '',
            $_SESSION['EnderecoShippingAddressMeta']['enderecoamsstatus'] ?? '',
            $_SESSION['EnderecoShippingAddressMeta']['enderecoamspredictions'] ?? ''
        );

        $this->addBillingAddressToConfirmationPage(
            $document,
            $smarty,
            $_SESSION['Kunde']->cLand ?? '',
            $_SESSION['Kunde']->cPLZ ?? '',
            $_SESSION['Kunde']->cOrt ?? '',
            $_SESSION['Kunde']->cStrasse ?? '',
            $_SESSION['Kunde']->cHausnummer ?? '',
            $_SESSION['Kunde']->cAdressZusatz ?? '',
            $_SESSION['EnderecoBillingAddressMeta']['enderecoamsts'] ?? '',
            $_SESSION['EnderecoBillingAddressMeta']['enderecoamsstatus'] ?? '',
            $_SESSION['EnderecoBillingAddressMeta']['enderecoamspredictions'] ?? ''
        );

        $this->addShippingAddressToConfirmationPage(
            $document,
            $smarty,
            $_SESSION['Lieferadresse']->cLand ?? '',
            $_SESSION['Lieferadresse']->cPLZ ?? '',
            $_SESSION['Lieferadresse']->cOrt ?? '',
            $_SESSION['Lieferadresse']->cStrasse ?? '',
            $_SESSION['Lieferadresse']->cHausnummer ?? '',
            $_SESSION['Lieferadresse']->cAdressZusatz ?? '',
            $_SESSION['EnderecoShippingAddressMeta']['enderecoamsts'] ?? '',
            $_SESSION['EnderecoShippingAddressMeta']['enderecoamsstatus'] ?? '',
            $_SESSION['EnderecoShippingAddressMeta']['enderecoamspredictions'] ?? ''
        );

        $this->includeSDK($document, $smarty);
    }
}