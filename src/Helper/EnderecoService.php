<?php

namespace Plugin\endereco_jtl5_client\src\Helper;

use JTL\Checkout\Bestellung;
use JTL\Checkout\Lieferadresse;
use JTL\Customer\Customer;
use JTL\Customer\DataHistory;
use JTL\DB\NiceDB;
use JTL\DB\DbInterface;
use JTL\Helpers\Text;
use JTL\Plugin\Plugin;
use JTL\Plugin\PluginInterface;
use Plugin\endereco_jtl5_client\src\Structures\AddressCheckResult;
use InvalidArgumentException;
use Exception;

class EnderecoService
{
    public PluginInterface $plugin;
    public string $clientInfo;
    private DbInterface $dbConnection;

    /**
     * constructor
     *
     * @param PluginInterface $oPlugin
     * @param DbInterface     $dbConnection
     */
    public function __construct(PluginInterface $oPlugin, DbInterface $dbConnection)
    {
        $this->plugin = $oPlugin;
        $this->dbConnection = $dbConnection;
        $this->clientInfo = 'Endereco JTL5 Client v' . $oPlugin->getMeta()->getVersion();
    }

    /**
     * Checks if the API key is set and returns its status.
     *
     * This method determines if the API key necessary for the plugin's operation
     * is set in the plugin's configuration. It checks the configuration value for
     * 'endereco_jtl5_client_api_key' and returns true if it's not empty, indicating
     * that the API key is set and presumably valid.
     *
     * @return bool Returns true if the API key is set, false otherwise.
     */
    public function isReady(): bool
    {
        $isApiKeySet = !empty($this->plugin->getConfig()->getValue('endereco_jtl5_client_api_key'));
        return $isApiKeySet;
    }

    /**
     * Finds and retrieves session IDs based on the POST request data.
     *
     * This method processes a POST request and extracts session IDs. It looks for
     * specific POST variables ending with '_session_counter' and pairs them with
     * corresponding '_session_id' variables. Only session IDs associated with a
     * positive '_session_counter' value are considered.
     *
     * @return array<string> An array of session IDs that meet the criteria. If the request
     *               method is not POST or if no matching sessions are found, an
     *               empty array is returned.
     */
    public function findSessions(): array
    {
        $accountableSessionIds = [];
        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            foreach ($_POST as $sVarName => $sVarValue) {
                if ((strpos($sVarName, '_session_counter') !== false) && 0 < intval($sVarValue)) {
                    // Compute the corresponding session ID name
                    $sSessionIdName = str_replace('_session_counter', '', $sVarName) . '_session_id';
                    if (isset($_POST[$sSessionIdName])) {
                        // Cast the session ID to string to ensure the value type is string
                        $sessionId = (string) $_POST[$sSessionIdName];
                        // Use the session ID as key to avoid duplicates, with a dummy value
                        $accountableSessionIds[$sessionId] = true;
                    }
                }
            }
            // Extract the keys (session IDs) and ensure they are strings
            $accountableSessionIds = array_keys($accountableSessionIds);
        }
        return $accountableSessionIds;
    }

    /**
     * Generates a unique session ID.
     *
     * This method creates a session ID using cryptographically secure random bytes.
     * It conforms to the UUID version 4 standard. The generated ID is a string
     * formatted as a universally unique identifier (UUID), which is typically
     * used for identifying information that needs to be unique within a system
     * or network. The format of the UUID is 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'
     * where 'x' is a random hexadecimal digit and 'y' is a hexadecimal digit
     * representing 8, 9, A, or B.
     *
     * @return string A unique UUID string representing the session ID.
     */
    public function generateSesionId()
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Validates and checks an address using the AddressCheck service.
     *
     * This method takes an address object, which can be of type Customer, Lieferadresse,
     * or DeliveryAddressTemplate, and performs an address check using an external service.
     * It handles different address formats, constructs a request with necessary parameters,
     * and sends this request to the address checking service.
     *
     * The method generates a unique session ID for the transaction, constructs the request
     * payload based on the provided address details, and handles the response. It also
     * initiates accounting and conversion tracking after the address check.
     *
     * In case of an error during the process, the method should handle the exception
     * appropriately (currently marked as TODO).
     *
     * @param mixed $address The address to be checked.
     *
     * @return AddressCheckResult The result of the address check, encapsulated in an AddressCheckResult object.
     */
    public function checkAddress($address): AddressCheckResult
    {
        if (
            !$this->isObjectCustomer($address) &&
            !$this->isObjectDeliveryAddress($address) &&
            !$this->isObjectDeliveryAddressTemplate($address)
        ) {
            throw new InvalidArgumentException(
                'Address must be of type Lieferadresse, DeliveryAddressTemplate, or Customer'
            );
        }

        // Load config.
        $config = $this->plugin->getConfig();

        // Generate Session ID.
        $sessionId = $this->generateSesionId();

        // Create address check result.
        $addressCheckResult = new AddressCheckResult();

        // Check address.
        try {
            $message = array(
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'addressCheck',
                'params' => array(
                    'language' => 'de',
                    'country' => strtoupper($address->cLand),
                    'postCode' => html_entity_decode($address->cPLZ),
                    'cityName' => html_entity_decode($address->cOrt),
                )
            );

            if (empty(trim($address->cHausnummer))) {
                $message['params']['streetFull'] = html_entity_decode($address->cStrasse);
            } else {
                $message['params']['street'] = html_entity_decode($address->cStrasse);
                $message['params']['houseNumber'] = html_entity_decode($address->cHausnummer);
            }

            $message['params']['additionalInfo'] = $address->cAdressZusatz ?? '';

            $newHeaders = array(
                'Content-Type' => 'application/json',
                'X-Auth-Key' => $config->getValue('endereco_jtl5_client_api_key'),
                'X-Transaction-Id' => $sessionId,
                'X-Transaction-Referer' => $_SERVER['HTTP_REFERER'] ?? 'not_set',
                'X-Agent' => $this->clientInfo,
            );

            $response = $this->sendRequest($message, $newHeaders);

            // Send doAccounting and doConversion.
            $this->doAccountings([$sessionId]);

            if (array_key_exists('result', $response)) {
                $_SESSION['EnderecoRequestCache'][$this->createRequestKey($message)] = $response;
            }

            $addressCheckResult->digestResponse($response);
        } catch (\Exception $e) {
            // TODO: log error
        }

        return $addressCheckResult;
    }

    /**
     * Looks up the result of a previous address check in the cache.
     *
     * This method attempts to retrieve the result of a previous address verification process from a cache,
     * based on the provided address object. It constructs a request message based on the address details,
     * uses it to generate a unique cache key, and then checks if the cache (stored in the session
     * under 'EnderecoRequestCache') contains a response corresponding to this key. If a cached response is
     * found, it is used to create and return an AddressCheckResult object.
     *
     * The address parameter can be an instance of Customer, Lieferadresse, or DeliveryAddressTemplate. These
     * objects must have properties for country code (`cLand`), postal code (`cPLZ`), city name (`cOrt`),
     * street name (`cStrasse`), house number (`cHausnummer`), and optionally an additional
     * address line (`cAdressZusatz`). The method handles different address formats by checking if the house
     * number is provided and adjusts the request message accordingly.
     *
     * @param mixed $address The address object to look up in the cache.
     *
     * @return AddressCheckResult An instance of AddressCheckResult that contains the outcome of
     *                            the address check or nothing.
     */
    public function lookupInCache($address): AddressCheckResult
    {
        if (
            !$this->isObjectCustomer($address) &&
            !$this->isObjectDeliveryAddress($address) &&
            !$this->isObjectDeliveryAddressTemplate($address)
        ) {
            throw new InvalidArgumentException(
                'Address must be of type Lieferadresse, DeliveryAddressTemplate, or Customer'
            );
        }

        // Create address check result.
        $addressCheckResult = new AddressCheckResult();

        // Create a fake meta.
        $message = array(
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'addressCheck',
            'params' => array(
                'language' => 'de',
                'country' => strtoupper($address->cLand),
                'postCode' => html_entity_decode($address->cPLZ),
                'cityName' => html_entity_decode($address->cOrt),
            )
        );

        if (empty(trim($address->cHausnummer))) {
            $message['params']['streetFull'] = html_entity_decode($address->cStrasse);
        } else {
            $message['params']['street'] = html_entity_decode($address->cStrasse);
            $message['params']['houseNumber'] = html_entity_decode($address->cHausnummer);
        }

        $message['params']['additionalInfo'] = $address->cAdressZusatz ?? '';

        $response = $_SESSION['EnderecoRequestCache'][$this->createRequestKey($message)] ?? null;

        if (!empty($response)) {
            $addressCheckResult->digestResponse($response);
        }

        return $addressCheckResult;
    }

    /**
     * Generates a base64-encoded string representing the JSON-encoded request array
     * after sorting the 'params' sub-array by its keys alphabetically.
     *
     * This function is useful for creating a unique, encoded key for a given request array,
     * which can then be used for caching, logging, or other purposes where a consistent,
     * reproducible string representation of the request is needed.
     *
     * @param array<string,mixed> $requestArray The request array to be processed. This array must include
     *                            a 'params' key with an array as its value. The 'params'
     *                            array will be sorted alphabetically by key before encoding.
     *
     * @return string A base64-encoded string representing the JSON-encoded request array
     *                with its 'params' sub-array sorted.
     *
     * Note: It is assumed that the 'params' key is always present in the input array. If 'params'
     *       is not set or is not an array, this function may not behave as expected, potentially
     *       leading to errors or unexpected behavior.
     */
    public function createRequestKey(array $requestArray): string
    {
        unset($requestArray['jsonrpc']);
        unset($requestArray['id']);
        ksort($requestArray['params']);

        $requestString = json_encode($requestArray);

        return $requestString ? base64_encode($requestString) : '';
    }

    /**
     * Updates the address in the database.
     *
     * @param mixed $addressObject The address object to be updated.
     *
     * @return void
     */
    public function updateAddressInDB($addressObject): void
    {
        if (
            !$this->isObjectCustomer($addressObject) &&
            !$this->isObjectDeliveryAddress($addressObject) &&
            !$this->isObjectDeliveryAddressTemplate($addressObject)
        ) {
            throw new InvalidArgumentException(
                'Address must be of type Lieferadresse, DeliveryAddressTemplate, or Customer'
            );
        }

        if ($addressObject instanceof Customer) {
            $addressObject->updateInDB();
        } else {
            $addressObject->update();
        }
    }

    /**
     * Updates the address in the session.
     *
     * @param mixed  $addressObject The address object to be updated.
     *
     * @return void
     */
    public function updateAddressInSession($addressObject): void
    {
        if ($addressObject instanceof Customer) {
            $space = 'Kunde';
            if (!empty($addressObject->kKunde)) {
                DataHistory::saveHistory($_SESSION[$space], $addressObject, DataHistory::QUELLE_BESTELLUNG);
            }
        } elseif ($addressObject instanceof Lieferadresse) {
            $space = 'Lieferadresse';
        } elseif ($this->isObjectDeliveryAddressTemplate($addressObject)) {
            $addressObject = $addressObject->getDeliveryAddress();
            $space = 'Lieferadresse';
        } else {
            return;
        }

        $_SESSION[$space] = $addressObject;
    }

    /**
     * Applies automatic corrections to an address object based on the results of an address check.
     *
     * This method takes an address object (Customer, Lieferadresse, or DeliveryAddressTemplate) and
     * an AddressCheckResult object. If the AddressCheckResult indicates that an automatic correction
     * is available, this method updates the address object with the corrected address details.
     *
     * The corrections are applied based on a predefined mapping between the fields in the address
     * check result and the corresponding properties in the address object. Only the fields present
     * in the autocorrection result are updated.
     *
     * @param mixed $addressObject The original address object to be corrected.
     * @param AddressCheckResult $checkResult The result from the address check containing potential corrections.
     *
     * @return mixed The address object after applying the corrections.
     */
    public function applyAutocorrection(
        $addressObject,
        AddressCheckResult $checkResult
    ): mixed {
        if (
            !$this->isObjectCustomer($addressObject) &&
            !$this->isObjectDeliveryAddress($addressObject) &&
            !$this->isObjectDeliveryAddressTemplate($addressObject)
        ) {
            throw new InvalidArgumentException(
                'Address must be of type Lieferadresse, DeliveryAddressTemplate, or Customer'
            );
        }

        if ($checkResult->isAutomaticCorrection()) {
            $correctionArray = $checkResult->getAutocorrectionArray();
            $mapping = [
                'countryCode' => 'cLand',
                'postalCode' => 'cPLZ',
                'locality' => 'cOrt',
                'streetName' => 'cStrasse',
                'buildingNumber' => 'cHausnummer',
                'additionalInfo' => 'cAdressZusatz',
            ];

            foreach ($correctionArray as $key => $value) {
                if (array_key_exists($key, $mapping)) {
                    $propertyName = $mapping[$key];

                    // Fix for when API returns lower cased country code.
                    if ('countryCode' === $key) {
                        $value = strtoupper($value);
                    }

                    $addressObject->$propertyName = $value;
                }
            }
        }

        return $addressObject;
    }

    /**
     * Updates the address metadata in the session.
     *
     * This function takes a timestamp, status(es), prediction(s), and a space identifier
     * to update address-related metadata in the session. If `statuses` or `predictions`
     * are provided as arrays, they are converted to a string and JSON format respectively.
     * The function updates or initializes the address metadata in the session under
     * the given `space`.
     *
     * @param string $timestamp The timestamp associated with the address update.
     * @param mixed  $statuses The status or an array of statuses related to the address.
     * @param mixed  $predictions The prediction or an array of predictions related to the address.
     * @param string $space The session key under which the address metadata is stored.
     *
     * @return void
     */
    public function updateAddressMetaInSession(
        string $timestamp,
        $statuses,
        $predictions,
        string $space
    ): void {
        if (is_array($statuses)) {
            $statuses = implode(',', $statuses);
        }

        if (is_array($predictions)) {
            $predictions = json_encode($predictions);
        }

        if (!array_key_exists($space, $_SESSION)) {
            $_SESSION[$space] = [
                'enderecoamsstatus' => $statuses,
                'enderecoamspredictions' => $predictions,
                'enderecoamsts' => $timestamp
            ];
        } else {
            $_SESSION[$space]['enderecoamsstatus'] = $statuses;
            $_SESSION[$space]['enderecoamspredictions'] = $predictions;
            $_SESSION[$space]['enderecoamsts'] = $timestamp;
        }
    }

    /**
     * Updates address metadata in the session cache based on given address data, statuses, and predictions.
     *
     * This method processes address data along with status and prediction information to construct
     * a request object and a corresponding fake response. The response includes the processed statuses
     * and predictions (converted to a specific format if necessary) and is stored in the session cache.
     * The method dynamically handles string or array inputs for statuses and predictions, converting
     * strings to arrays as needed. It constructs a JSON-RPC style message for an 'addressCheck' method
     * call, incorporating the provided address data and additional information.
     *
     * @param array<string,string> $addressData An associative array containing address components, such as
     *                           'countryCode', 'postalCode', 'locality', 'buildingNumber', 'streetName',
     *                           and optionally 'additionalInfo'.
     * @param mixed $statuses A single status or an array of statuses related to the address
     *                               check. If a string is provided, it should contain statuses separated
     *                               by commas.
     * @param mixed $predictions A JSON string or an array of prediction data. If a string is
     *                                  provided, it is decoded into an array.
     * @return void The method does not return a value but updates the session cache with the constructed
     *              response.
     */
    public function updateAddressMetaInCache(
        array $addressData,
        $statuses,
        $predictions
    ) {
        if (is_string($predictions)) {
            $predictions = json_decode($predictions, true);
        }

        if (is_string($statuses)) {
            $statuses = explode(',', $statuses);
        }

        // Recreate the request object.
        $message = array(
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'addressCheck',
            'params' => array(
                'language' => 'de',
                'country' => strtoupper($addressData['countryCode']),
                'postCode' => $addressData['postalCode'],
                'cityName' => $addressData['locality'],
            )
        );

        if (!empty(trim($addressData['buildingNumber']))) {
            $message['params']['houseNumber'] = $addressData['buildingNumber'];
            $message['params']['street'] = $addressData['streetName'];
        } else {
            $message['params']['streetFull'] = $addressData['streetName'];
        }

        $message['params']['additionalInfo'] = $addressData['additionalInfo'] ?? '';

        $fakeAddressCheckResult = new AddressCheckResult();

        // Recreate response.
        $response = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'status' => $statuses,
                'predictions' => $fakeAddressCheckResult->transformPredictionsToOuterFormat($predictions)
            ]
        ];

        if (!isset($_SESSION['EnderecoRequestCache'])) {
            $_SESSION['EnderecoRequestCache'] = [];
        }

        $_SESSION['EnderecoRequestCache'][$this->createRequestKey($message)] = $response;
    }

    /**
     * Initializes address metadata from DB data.
     *
     * If $data is an stdClass, populates addressMeta properties
     * with $data values or defaults. Returns the addressMeta object.
     *
     * @param mixed $data Data to populate addressMeta.
     * @return \stdClass Populated addressMeta object.
     */
    public function createAddressMetaFromDBData($data): \stdClass
    {
        $addressMeta = new \stdClass();
        $addressMeta->enderecoamsts = 0;
        $addressMeta->enderecoamsstatus = '';
        $addressMeta->enderecoamspredictions = '';

        if ($data instanceof \stdClass) {
            $addressMeta->enderecoamsts = $data->enderecoamsts ?? 0;
            $addressMeta->enderecoamsstatus = $data->enderecoamsstatus ?? '';
            $addressMeta->enderecoamspredictions = $data->enderecoamspredictions ?? '';
        }

        return $addressMeta;
    }

    /**
     * Determines whether the billing address is different from the shipping address.
     *
     * This method checks the session data to determine if the billing address (Kunde) and
     * shipping address (Lieferadresse) are different. It considers various scenarios, such as
     * when a shipping address preset is selected or when the shipping address is not set.
     *
     * @return bool Returns true if the billing and shipping addresses are different, false otherwise.
     */
    public function isBillingDifferentFromShipping(): bool
    {
        // Check if a shipping address preset is selected - always consider different in this case
        if (!empty($_SESSION['shippingAddressPresetID'])) {
            return true;
        }

        // If shipping address (Lieferadresse) is not set in the session, it's the same as the billing address
        if (!isset($_SESSION['Lieferadresse']) || empty($_SESSION['Lieferadresse'])) {
            return false;
        }

        $sameCountry = $this->compareStringsDifferentEncodings(
            $_SESSION['Kunde']->cLand ?? '',
            $_SESSION['Lieferadresse']->cLand ?? ''
        );

        $samePostalCode = $this->compareStringsDifferentEncodings(
            $_SESSION['Kunde']->cPLZ ?? '',
            $_SESSION['Lieferadresse']->cPLZ ?? ''
        );

        $sameLocality = $this->compareStringsDifferentEncodings(
            $_SESSION['Kunde']->cOrt ?? '',
            $_SESSION['Lieferadresse']->cOrt ?? ''
        );

        $sameStreet = $this->compareStringsDifferentEncodings(
            $_SESSION['Kunde']->cStrasse ?? '',
            $_SESSION['Lieferadresse']->cStrasse ?? ''
        );

        $sameBuildingNumber = $this->compareStringsDifferentEncodings(
            $_SESSION['Kunde']->cHausnummer ?? '',
            $_SESSION['Lieferadresse']->cHausnummer ?? ''
        );

        $sameAddition = $this->compareStringsDifferentEncodings(
            $_SESSION['Kunde']->cAdressZusatz ?? '',
            $_SESSION['Lieferadresse']->cAdressZusatz ?? ''
        );

        $sameAddress = $sameCountry
            && $samePostalCode
            && $sameLocality
            && $sameStreet
            && $sameBuildingNumber
            && $sameAddition;

        // If any of the fields differ, the addresses are considered different
        return !$sameAddress;
    }

    /**
     * Compares two strings that may be in different encodings, converting them to a common encoding.
     *
     * This function takes two strings and an optional target encoding (defaulting to UTF-8). It first
     * detects the encoding of each string and then converts both to the target encoding. After conversion,
     * it also decodes any HTML entities. The strings are then compared for equality.
     *
     * @param string $str1 The first string to compare.
     * @param string $str2 The second string to compare.
     * @param string $targetEncoding (optional) The encoding to convert both strings to, defaults to 'UTF-8'.
     *
     * @return bool Returns true if the converted strings are equal, false otherwise.
     */
    public function compareStringsDifferentEncodings(string $str1, string $str2, $targetEncoding = 'UTF-8')
    {
        // Detect encodings or use a default/fallback encoding
        $detectedEncodingStr1 = mb_detect_encoding($str1) ?: 'ISO-8859-1'; // Fallback to 'ISO-8859-1'
        $detectedEncodingStr2 = mb_detect_encoding($str2) ?: 'ISO-8859-1'; // Fallback to 'ISO-8859-1'

        // Convert both strings to the target encoding
        $convertedStr1 = trim(mb_convert_encoding($str1, $targetEncoding, $detectedEncodingStr1));
        $convertedStr2 = trim(mb_convert_encoding($str2, $targetEncoding, $detectedEncodingStr2));

        // Assuming Text::unhtmlentities() is a method to decode HTML entities
        // Note: PHP's html_entity_decode() could be used if Text::unhtmlentities() is not available
        $convertedStr1 = Text::unhtmlentities($convertedStr1);
        $convertedStr2 = Text::unhtmlentities($convertedStr2);

        // Compare the converted strings
        return strcmp($convertedStr1, $convertedStr2) == 0;
    }


    /**
     * Clears specific metadata related to billing and shipping addresses from the session.
     *
     * This method removes 'EnderecoBillingAddressMeta' and 'EnderecoShippingAddressMeta'
     * from the session, effectively resetting any stored metadata for billing and shipping
     * addresses. It is typically used to ensure that outdated or irrelevant address metadata
     * does not persist in the session.
     */
    public function clearMetaFromSession(): void
    {
        unset($_SESSION['EnderecoBillingAddressMeta']);
        unset($_SESSION['EnderecoShippingAddressMeta']);
    }

    /**
     * Checks if the provided object is an instance of the Customer class.
     *
     * @param mixed $object The object to check.
     * @return bool Returns true if the object is an instance of Customer, false otherwise.
     */
    public function isObjectCustomer($object)
    {
        return $object instanceof Customer;
    }

    /**
     * Checks if the provided object is an instance of the Lieferadresse class.
     *
     * @param mixed $object The object to check.
     * @return bool Returns true if the object is an instance of Lieferadresse, false otherwise.
     */
    public function isObjectDeliveryAddress($object)
    {
        return $object instanceof Lieferadresse;
    }

    /**
     * Checks if the DeliveryAddressTemplate class exists and the provided object is not an
     * instance of this class.
     *
     * This function is useful for environments where the DeliveryAddressTemplate class might not
     * be available. It ensures that the class exists before performing the instance check.
     *
     * @param mixed $object The object to check.
     *
     * @return bool Returns true if the DeliveryAddressTemplate class exists and the object is
     *              not an instance of it, false otherwise.
     */
    public function isObjectDeliveryAddressTemplate($object)
    {
        return class_exists('JTL\Checkout\DeliveryAddressTemplate')
            && !$object instanceof \JTL\Checkout\DeliveryAddressTemplate;
    }

    /**
    * Checks if the address metadata is empty or if its 'enderecoamsstatus' property is empty.
    *
    * This function evaluates two conditions:
    * 1. If the provided $addressMeta parameter itself is empty.
    * 2. If the 'enderecoamsstatus' property of the $addressMeta object is empty.
    * It returns true if either of these conditions is met, indicating that either
    * the metadata is not provided or it lacks a status.
    *
    * @param mixed $addressMeta The address metadata object to check. Can be any type, but
    *                           typically an object with an 'enderecoamsstatus' property.
    *
    * @return bool Returns true if the metadata is empty or the 'enderecoamsstatus' property is empty, false otherwise.
    */
    public function isStatusEmpty($addressMeta): bool
    {
        return empty($addressMeta) || empty($addressMeta->enderecoamsstatus);
    }


    /**
     * Updates the address metadata in the database.
     *
     * This function is responsible for updating address metadata in the database based on
     * the provided address object (either Customer or DeliveryAddressTemplate), timestamp,
     * status(es), and prediction(s). The function handles the conversion of `statuses` and
     * `predictions` arrays into a string and JSON format, respectively. It then determines
     * the appropriate IDs (customer or delivery address) based on the type of address object
     * provided. The function also sanitizes the input data to prevent XSS attacks before
     * inserting or updating the database record.
     *
     * @param mixed $addressObject An object representing either a customer or a delivery address.
     * @param string $timestamp The timestamp associated with the address update.
     * @param mixed $statuses The status or an array of statuses related to the address.
     * @param mixed $predictions The prediction or an array of predictions related to the address.
     *
     * @return void
     */
    public function updateAddressMetaInDB(
        $addressObject,
        string $timestamp,
        $statuses,
        $predictions
    ): void {
        if (
            !$this->isObjectCustomer($addressObject) &&
            !$this->isObjectDeliveryAddress($addressObject) &&
            !$this->isObjectDeliveryAddressTemplate($addressObject)
        ) {
            throw new InvalidArgumentException(
                'Address must be of type Lieferadresse, DeliveryAddressTemplate, or Customer'
            );
        }

        if (is_array($statuses)) {
            $statuses = implode(',', $statuses);
        }

        if (is_array($predictions)) {
            $predictions = json_encode($predictions);
        }

        if ($addressObject instanceof Customer) {
            $billingAddressId = $addressObject->kKunde;
            $shippingAddressId = null;
        } else {
            $billingAddressId = null;
            $shippingAddressId = $addressObject->kLieferadresse;
        }

        // Prepare meta values
        $filteredTs = Text::filterXSS($timestamp);
        $filteredStatus = Text::filterXSS($statuses);

        if (is_string($predictions)) {
            $decodedPredictions = json_decode($predictions, true);

            // Check if json_decode returned a valid result.
            if ($decodedPredictions !== null) {
                $filteredPredictions = $predictions;
            } else {
                $filteredPredictions = '[]';
            }
        } elseif (is_array($predictions)) {
            // If predictions is an array, encode it as JSON.
            $filteredPredictions = json_encode($predictions);
        } else {
            $filteredPredictions = '[]';
        }

        // SQL query with placeholders
        $sql = "INSERT INTO `xplugin_endereco_jtl5_client_tams` 
            (`kKunde`, `kRechnungsadresse`, `kLieferadresse`, `enderecoamsts`, 
             `enderecoamsstatus`, `enderecoamspredictions`, `last_change_at`)
         VALUES 
            (:kKunde, NULL, :kLieferadresse, :enderecoamsts, :enderecoamsstatus, 
             :enderecoamspredictions, now())
         ON DUPLICATE KEY UPDATE    
            `kKunde`=:kKunde, `kLieferadresse`=:kLieferadresse, `enderecoamsts`=:enderecoamsts, 
            `enderecoamsstatus`=:enderecoamsstatus, `enderecoamspredictions`=:enderecoamspredictions, 
            `last_change_at`=now()";


        // Prepare and execute the query
        $this->dbConnection->queryPrepared(
            $sql,
            [
                ':kKunde' => $billingAddressId,
                ':kLieferadresse' => $shippingAddressId,
                ':enderecoamsts' => $filteredTs,
                ':enderecoamsstatus' => $filteredStatus,
                ':enderecoamspredictions' => $filteredPredictions
            ],
            1
        );
    }

    /**
     * Retrieves metadata related to an order's address.
     *
     * Fetches address metadata such as status, pred
     * ictions, and timestamps from session data
     * based on whether the order has a delivery address or uses the customer's address.
     *
     * @param Bestellung $order The order for which address metadata is required.
     *
     * @return \stdClass An object containing the address metadata.
     */
    public function getOrderAddressMeta(Bestellung $order): \stdClass
    {
        $addressMeta = new \stdClass();
        $addressMeta->enderecoamsts = '';
        $addressMeta->enderecoamsstatus = '';
        $addressMeta->enderecoamspredictions = '';

        if (!empty($order->kLieferadresse)) {
            if (
                !empty($_SESSION['EnderecoShippingAddressMeta']) &&
                !empty($_SESSION['EnderecoShippingAddressMeta']['enderecoamsstatus'])
            ) {
                $addressMeta->enderecoamsstatus = $_SESSION['EnderecoShippingAddressMeta']['enderecoamsstatus'];
                $addressMeta->enderecoamspredictions
                    = $_SESSION['EnderecoShippingAddressMeta']['enderecoamspredictions'];
                $addressMeta->enderecoamsts = $_SESSION['EnderecoShippingAddressMeta']['enderecoamsts'];
            }
        } elseif ($order->kKunde) {
            if (
                !empty($_SESSION['EnderecoBillingAddressMeta']) &&
                !empty($_SESSION['EnderecoBillingAddressMeta']['enderecoamsstatus'])
            ) {
                $addressMeta->enderecoamsstatus = $_SESSION['EnderecoBillingAddressMeta']['enderecoamsstatus'];
                $addressMeta->enderecoamspredictions
                    = $_SESSION['EnderecoBillingAddressMeta']['enderecoamspredictions'];
                $addressMeta->enderecoamsts = $_SESSION['EnderecoBillingAddressMeta']['enderecoamsts'];
            }
        }

        return $addressMeta;
    }

    /**
     * Performs accounting actions for given session IDs and triggers a conversion event.
     *
     * This method iterates over an array of session IDs, sending a 'doAccounting' request
     * for each session ID to a remote service. After processing all session IDs, if at least
     * one 'doAccounting' request has been made, it additionally sends a 'doConversion' request.
     *
     * Each request includes relevant information such as session ID and configuration values,
     * and is sent using the `sendRequest` method. The method handles exceptions silently and
     * may log errors (logging implementation is marked as TODO).
     *
     * @param array<string> $sessionIds An array of session IDs for which to perform accounting.
     *
     * @return void
     */
    public function doAccountings($sessionIds): void
    {

        // Get sessionids.
        if (empty($sessionIds)) {
            return;
        }

        $config = $this->plugin->getConfig();

        $anyDoAccounting = false;

        foreach ($sessionIds as $sessionId) {
            try {
                $message = array(
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'doAccounting',
                    'params' => array(
                        'sessionId' => $sessionId
                    )
                );
                $newHeaders = array(
                    'Content-Type' => 'application/json',
                    'X-Auth-Key' => $config->getValue('endereco_jtl5_client_api_key'),
                    'X-Transaction-Id' => $sessionId,
                    'X-Transaction-Referer' => $_SERVER['HTTP_REFERER'],
                    'X-Agent' => $this->clientInfo,
                );
                $this->sendRequest($message, $newHeaders);
                $anyDoAccounting = true;
            } catch (\Exception $e) {
                // TODO: log error
            }
        }

        if ($anyDoAccounting) {
            try {
                $message = array(
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'doConversion',
                    'params' => array()
                );
                $newHeaders = array(
                    'Content-Type' => 'application/json',
                    'X-Auth-Key' => $config->getValue('endereco_jtl5_client_api_key'),
                    'X-Transaction-Id' => 'not_required',
                    'X-Transaction-Referer' => $_SERVER['HTTP_REFERER'],
                    'X-Agent' => $this->clientInfo,
                );
                $this->sendRequest($message, $newHeaders);
            } catch (\Exception $e) {
                // Do nothing.
            }
        }
    }

    /**
     * Sends an HTTP POST request to a remote service.
     *
     * This method constructs and sends an HTTP POST request using cURL. It takes a
     * request body and an array of headers as parameters, encodes the body as JSON,
     * and appends the appropriate headers. The request is sent to the URL specified
     * in the plugin configuration ('endereco_jtl5_client_remote_url'). The method
     * returns the decoded JSON response.
     *
     * @param array<string,mixed> $body The request body to be sent, which will be JSON encoded.
     * @param array<string,string> $headers An associative array of headers to be included in the request.
     *
     * @return array<mixed> The decoded JSON response from the remote service.
     */
    public function sendRequest(array $body, array $headers): array
    {
        $config = $this->plugin->getConfig();
        $serviceUrl = $config->getValue('endereco_jtl5_client_remote_url');
        $ch = curl_init(trim($serviceUrl));

        if ($ch === false) {
            return [];
        }

        $dataString = json_encode($body);
        if ($dataString === false) {
            return [];
        }

        $parsedHeaders = array();
        foreach ($headers as $headerName => $headerValue) {
            $parsedHeaders[] = $headerName . ': ' . $headerValue;
        }
        $parsedHeaders[] = 'Content-Length: ' . strlen($dataString);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            $parsedHeaders
        );

        $result = curl_exec($ch);
        if ($result === false) {
            return [];
        }

        $result = '' . $result;

        $decodedResult = json_decode($result, true);
        if ($decodedResult === null && json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        return $decodedResult;
    }
}
