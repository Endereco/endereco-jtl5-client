<?php

namespace Plugin\endereco_jtl5_client\src\Structures;

use JTL\Checkout\DeliveryAddressTemplate;
use JTL\Checkout\Lieferadresse;
use JTL\Customer\Customer;

/**
 * Represents the result of an address check, including predictions, statuses, and a timestamp.
 */
class AddressCheckResult
{
    /**
     * @var array<array<string,string>> List of address prediction arrays.
     */
    private array $predictions = [];

    /**
     * @var array<string> List of status strings related to the address check.
     */
    private array $statuses = [];

    /**
     * @var int Timestamp of when the address check was processed.
     */
    private int $timestamp = 0;

    /**
     * Creates an AddressCheckResult instance from an AddressMeta instance.
     *
     * @param AddressMeta $addressMeta The AddressMeta instance to recreate from.
     *
     * @return AddressCheckResult Returns an instance of AddressCheckResult.
     */
    public static function createFromMeta(AddressMeta $addressMeta): AddressCheckResult
    {
        $newInstance = new AddressCheckResult();
        $newInstance->timestamp = $addressMeta->getTimestamp();
        $newInstance->statuses = $addressMeta->getStatus();
        $newInstance->predictions = $addressMeta->getPredictions();

        return $newInstance;
    }

    /**
     * Transforms address predictions from an external format to an internal one.
     *
     * @param array<array<string,string>> $predictions Array of address predictions in external format.
     *
     * @return array<array<string,string>> Array of address predictions in internal format.
     */
    public function transformPredictionsToInnerFormat(array $predictions): array
    {
        $newPredictions = [];
        $mapping = [
            'postCode' => 'postalCode',
            'country' => 'countryCode',
            'cityName' => 'locality',
            'street' => 'streetName',
            'houseNumber' => 'buildingNumber'
        ];

        foreach ($predictions as $prediction) {
            $newPrediction = [];
            foreach ($prediction as $key => $value) {
                if (array_key_exists($key, $mapping)) {
                    $newPrediction[$mapping[$key]] = $value;
                } else {
                    $newPrediction[$key] = $value;
                }
            }
            $newPredictions[] = $newPrediction;
        }

        return $newPredictions;
    }

    /**
     * Transforms address predictions from an internal format to an external one.
     *
     * @param array<array<string,string>> $predictions Array of address predictions in internal format.
     *
     * @return array<array<string,string>> Array of address predictions in external format.
     */
    public function transformPredictionsToOuterFormat(array $predictions): array
    {
        $newPredictions = [];
        $mapping = [
            'postalCode' => 'postCode',
            'countryCode' => 'country',
            'locality' => 'cityName',
            'streetName' => 'street',
            'buildingNumber' => 'houseNumber'
        ];

        foreach ($predictions as $prediction) {
            $newPrediction = [];
            foreach ($prediction as $key => $value) {
                if (array_key_exists($key, $mapping)) {
                    $newPrediction[$mapping[$key]] = $value;
                } else {
                    $newPrediction[$key] = $value;
                }
            }
            $newPredictions[] = $newPrediction;
        }

        return $newPredictions;
    }

    /**
     * Processes the response from an address check API and updates the object's state.
     *
     * @param array<mixed> $response The API response array.
     *
     * @return void
     */
    public function digestResponse(array $response): void
    {
        if (!array_key_exists('result', $response)) {
            return;
        }

        $this->predictions = $this->transformPredictionsToInnerFormat($response['result']['predictions']);
        $this->statuses = $response['result']['status'];
        $this->timestamp = time();
    }

    /**
     * Determines whether the address needs automatic correction.
     *
     * @return bool True if the address needs automatic correction, false otherwise.
     */
    public function isAutomaticCorrection(): bool
    {
        return in_array('address_minor_correction', $this->statuses);
    }

    /**
     * Determines whether the address was confirmed by the customer.
     *
     * @return bool True if the address was confirmed by the customer, false otherwise.
     */
    public function isConfirmedByCustomer(): bool
    {
        return in_array('address_selected_by_customer', $this->statuses);
    }

    /**
     * Retrieves metadata related to the address check.
     *
     * @return AddressMeta An object containing metadata about the address check.
     */
    public function getMeta(): AddressMeta
    {
        $addressMeta = new AddressMeta();
        $addressMeta->assign(
            $this->timestamp,
            $this->statuses,
            $this->predictions
        );

        return $addressMeta;
    }

    /**
     * Gets the first prediction array if automatic correction was applied.
     *
     * @return array<string,string> The first prediction array, or an empty array if none exist.
     */
    public function getAutocorrectionArray(): array
    {
        return $this->predictions[0] ?? [];
    }

    /**
     * Retrieves metadata after an automatic correction was applied.
     *
     * @return AddressMeta An object containing metadata after automatic correction.
     */
    public function getMetaAfterAutomaticCorrection(): AddressMeta
    {
        $addressMeta = new AddressMeta();
        $statusMapping = [
            'countryCode' => 'country_code_correct',
            'subdivisionCode' => 'country_code_correct',
            'postalCode' => 'country_code_correct',
            'locality' => 'country_code_correct',
            'streetName' => 'country_code_correct',
            'buildingNumber' => 'country_code_correct',
            'additionalInfo' => 'country_code_correct',
        ];

        $firstPredictionKeys = array_keys($this->predictions[0]);
        $statuses = ['A1000', 'address_correct', 'address_selected_automatically'];
        foreach ($firstPredictionKeys as $key) {
            if (array_key_exists($key, $statusMapping)) {
                $statuses[] = $statusMapping[$key];
            }
        }

        $addressMeta->assign(
            $this->timestamp,
            $statuses,
            []
        );

        return $addressMeta;
    }

    /**
     * Serializes the address check statuses into a string.
     *
     * @return string The serialized status string.
     */
    public function getStatusSerialized(): string
    {
        return '';
    }

    /**
     * Serializes the address predictions into a string.
     *
     * @return string The serialized predictions string.
     */
    public function getPredictionsSerialized(): string
    {
        return '';
    }
}
