<?php

namespace Plugin\endereco_jtl5_client\src\Structures;

/**
 * Represents meta information of an address created by an address check.
 */
class AddressMeta
{
    /**
     * @var int The timestamp of the address meta, with 0 as default.
     */
    private $timestamp;

    /**
     * @var string[] The status of the address check, can be a string of comma-separated values or an array.
     */
    private $status;

    /**
     * @var mixed[] The predictions of the address check, can be a serialized JSON string or an array.
     */
    private $predictions;

    /**
     * Constructor for AddressMeta, initializing default values.
     */
    public function __construct()
    {
        $this->timestamp = 0;
        $this->status = [];
        $this->predictions = [];
    }

    /**
     * Mass assignment method for AddressMeta properties.
     *
     * @param int $timestamp The timestamp of the address meta.
     * @param mixed $status The status of the address check.
     * @param mixed $predictions The predictions of the address check.
     * @return self
     */
    public function assign($timestamp, $status, $predictions): self
    {
        $this->setTimestamp($timestamp);
        $this->setStatus($status);
        $this->setPredictions($predictions);

        return $this;
    }

    /**
     * Set timestamp.
     *
     * @param mixed $timestamp Timestamp as itn or string.
     *
     * @return void
     */
    public function setTimestamp($timestamp): void
    {
        if (is_string($timestamp)) {
            $timestamp = intval($timestamp);
        }

        $this->timestamp = $timestamp;
    }

    /**
     * Set the status.
     *
     * @param mixed $status Status to set. String or array.
     *
     * @return void
     */
    public function setStatus($status): void
    {
        if (is_string($status)) {
            $status = explode(',', $status);
        }

        if (empty($status)) {
            $status = [];
        }

        $this->status = $status;
    }

    /**
     * Set the predictions.
     *
     * @param mixed $predictions Predictions to set as JSON string or array.
     *
     * @return void
     */
    public function setPredictions($predictions): void
    {
        if (is_string($predictions)) {
            $predictions = json_decode($predictions, true);
        } elseif (is_null($predictions)) {
            $predictions = [];
        }

        if (empty($predictions)) {
            $predictions = [];
        }

        $this->predictions = $predictions;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * @return string[] The status.
     */
    public function getStatus(): array
    {
        return $this->status;
    }

    /**
     * @return string The status as a string.
     */
    public function getStatusAsString(): string
    {
        return implode(',', $this->status);
    }

    /**
     * @return mixed[] The predictions.
     */
    public function getPredictions(): array
    {
        return $this->predictions;
    }

    /**
     * @return string The predictions as a JSON string.
     */
    public function getPredictionsAsString(): string
    {
        $json = json_encode($this->predictions);
        if ($json === false) {
            return '[]';
        }
        return $json;
    }

    public function hasStatus(string $status): bool
    {
        return in_array($status, $this->status, true);
    }

    public function hasAnyStatus(): bool
    {
        return !empty($this->status);
    }
}
