<?php

namespace Plugin\endereco_jtl5_client\src\Structures;

use JTL\Checkout\DeliveryAddressTemplate;
use JTL\Checkout\Lieferadresse;
use JTL\Customer\Customer;

class AddressCheckResult
{
    private array $predictions = [];
    private array $statuses = [];
    private int $timestamp = 0;
    private bool $hasError = false;

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

    public function digestResponse(array $response): void
    {
        if (!array_key_exists('result', $response)) {
            $this->hasError = true;
            return;
        }

        $this->predictions = $this->transformPredictionsToInnerFormat($response['result']['predictions']);
        $this->statuses = $response['result']['status'];
        $this->timestamp = time();
    }

    public function isAutomaticCorrection(): bool
    {
        return in_array('address_minor_correction', $this->statuses);
    }

    public function isConfirmedByCustomer(): bool
    {
        return in_array('address_selected_by_customer', $this->statuses);
    }

    public function getMeta(): \stdClass
    {
        $meta = new \stdClass();
        $meta->enderecoamsts = (string) $this->timestamp;
        $meta->enderecoamsstatus = implode(',', $this->statuses);
        $meta->enderecoamspredictions = json_encode($this->predictions);

        return $meta;
    }

    public function getAutocorrectionArray(): array
    {
        return $this->predictions[0] ?? [];
    }

    public function getMetaAfterAutomaticCorrection(): \stdClass
    {
        $meta = new \stdClass();
        $meta->enderecoamsts = '' . $this->timestamp;

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

        $meta->enderecoamsstatus = implode(',', $statuses);
        $meta->enderecoamspredictions = '[]';

        return $meta;
    }

    public function getStatusSerialized(): string
    {
        return '';
    }

    public function getPredictionsSerialized(): string
    {
        return '';
    }
}
