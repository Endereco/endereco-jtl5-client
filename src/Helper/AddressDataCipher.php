<?php

declare(strict_types=1);

namespace Plugin\endereco_jtl5_client\src\Helper;

use JTL\Services\JTL\CryptoServiceInterface;
use JTL\Shop;
use Throwable;

/**
 * Encrypts the personal data of stored address predictions.
 *
 * Mirrors the shop core, which keeps the street of customer and order
 * addresses XTEA encrypted with BLOWFISH_KEY. The additional info is
 * encrypted as well, because it often carries names ("c/o ..."). All other
 * prediction fields stay readable, like their counterparts in the core.
 */
class AddressDataCipher
{
    private const ENCRYPTED_FIELDS = ['streetName', 'additionalInfo'];

    /**
     * Tells encrypted values apart from readable ones stored before the
     * encryption was introduced, which a missing marker alone cannot: it also
     * misses under a wrong key.
     */
    private const PREFIX = 'endereco:xtea:';

    /**
     * XTEA has no integrity check: decrypting with a wrong key does not fail,
     * it returns meaningless bytes. The marker is encrypted along with the
     * value, so a changed or lost BLOWFISH_KEY is detected instead of passing
     * those bytes on as address data.
     */
    private const MARKER = 'endereco:v1:';

    private const REASON_FORMAT = 'the stored content is not in the expected format';
    private const REASON_UNENCRYPTED = 'personal fields were stored unencrypted';
    private const REASON_DAMAGED = 'an encrypted value is damaged';
    private const REASON_KEY = 'an encrypted value cannot be decrypted with the current BLOWFISH_KEY'
        . ' (the key has changed or the value is damaged)';

    private CryptoServiceInterface $cryptoService;
    private bool $failureLogged = false;

    public function __construct(CryptoServiceInterface $cryptoService)
    {
        $this->cryptoService = $cryptoService;
    }

    /**
     * @param mixed[] $predictions Predictions in the internal format.
     *
     * @return mixed[]|null The predictions with their personal fields encrypted,
     *                      or null if they cannot be encrypted, e.g. because
     *                      BLOWFISH_KEY is not defined. They must then not be
     *                      stored at all: personal data is never stored readable.
     */
    public function encryptPredictions(array $predictions): ?array
    {
        $encryptedPredictions = $predictions;

        try {
            foreach ($encryptedPredictions as $index => $prediction) {
                if (!is_array($prediction)) {
                    continue;
                }

                foreach (self::ENCRYPTED_FIELDS as $field) {
                    $value = $prediction[$field] ?? null;
                    if ($this->isUnencryptedValue($value)) {
                        $encryptedPredictions[$index][$field] = $this->encryptValue($value);
                    }
                }
            }
        } catch (Throwable) {
            $this->logFailure(
                'Endereco: an address check result could not be encrypted, BLOWFISH_KEY is probably'
                . ' missing. The result is not stored and a previous result of the address is'
                . ' removed, the address counts as never checked.'
            );
            return null;
        }

        return $encryptedPredictions;
    }

    /**
     * Reads stored predictions for use. A stored result is usable only if it is
     * in the expected format, holds no readable personal fields and every
     * encrypted value decrypts with the current key. Anything else invalidates
     * the whole result, the reason is logged without personal data.
     *
     * @param string $stored The predictions column as stored in the database.
     *
     * @return mixed[]|null The readable predictions, or null if the stored result
     *                      is invalid.
     */
    public function readStoredPredictions(string $stored): ?array
    {
        $problem = $this->findStoredFormatProblem($stored);
        if ($problem !== null) {
            $this->logInvalidResult($problem);
            return null;
        }

        /** @var array<int, array<string, mixed>> $predictions Ensured by findStoredFormatProblem(). */
        $predictions = json_decode($stored, true);
        foreach ($predictions as $index => $prediction) {
            foreach (self::ENCRYPTED_FIELDS as $field) {
                $value = $prediction[$field] ?? null;
                if (!is_string($value) || $value === '') {
                    continue;
                }

                if (!$this->hasCipherTextShape($value)) {
                    $this->logInvalidResult(self::REASON_DAMAGED);
                    return null;
                }

                $decryptedValue = $this->decryptValue($value);
                if ($decryptedValue === null) {
                    $this->logInvalidResult(self::REASON_KEY);
                    return null;
                }

                $predictions[$index][$field] = $decryptedValue;
            }
        }

        return $predictions;
    }

    /**
     * Checks the stored predictions without decrypting them: the expected format
     * and no readable personal fields. Cheap enough to run over a whole table.
     *
     * @param string $stored The predictions column as stored in the database.
     */
    public function isStoredFormatValid(string $stored): bool
    {
        return $this->findStoredFormatProblem($stored) === null;
    }

    /**
     * Expected is a JSON list of predictions, each an object whose personal
     * fields, if present, are strings that are empty or encrypted.
     *
     * @return string|null The reason why the stored predictions are invalid, or
     *                     null if their format is valid.
     */
    private function findStoredFormatProblem(string $stored): ?string
    {
        $predictions = json_decode($stored, true);
        if (!is_array($predictions) || !array_is_list($predictions)) {
            return self::REASON_FORMAT;
        }

        foreach ($predictions as $prediction) {
            if (!is_array($prediction) || ($prediction !== [] && array_is_list($prediction))) {
                return self::REASON_FORMAT;
            }

            foreach (self::ENCRYPTED_FIELDS as $field) {
                $value = $prediction[$field] ?? null;
                if ($value !== null && !is_string($value)) {
                    return self::REASON_FORMAT;
                }
                if ($this->isUnencryptedValue($value)) {
                    return self::REASON_UNENCRYPTED;
                }
            }
        }

        return null;
    }

    /**
     * Checked upfront, because the XTEA implementation reads past the end of a
     * truncated cipher text instead of failing.
     */
    private function hasCipherTextShape(string $value): bool
    {
        if (!$this->isEncrypted($value)) {
            return false;
        }

        $rawCipherText = base64_decode(substr($value, strlen(self::PREFIX)), true);

        return $rawCipherText !== false && strlen($rawCipherText) >= 16 && strlen($rawCipherText) % 8 === 0;
    }

    private function encryptValue(string $value): string
    {
        return self::PREFIX . $this->cryptoService->encryptXTEA(self::MARKER . $value);
    }

    /**
     * @return string|null The plain value, or null if the value was not encrypted
     *                     with the current key.
     */
    private function decryptValue(string $value): ?string
    {
        if (!$this->hasCipherTextShape($value)) {
            return null;
        }

        try {
            // XTEA pads the plain text with spaces to full blocks.
            $plainText = rtrim($this->cryptoService->decryptXTEA(substr($value, strlen(self::PREFIX))), ' ');
        } catch (Throwable) {
            return null;
        }

        if (!str_starts_with($plainText, self::MARKER)) {
            return null;
        }

        return substr($plainText, strlen(self::MARKER));
    }

    private function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    /**
     * Tells whether a field value still has to be encrypted. Empty values are
     * left as they are, they carry no address data.
     *
     * @phpstan-assert-if-true non-empty-string $value
     */
    private function isUnencryptedValue(mixed $value): bool
    {
        return is_string($value) && $value !== '' && !$this->isEncrypted($value);
    }

    private function logInvalidResult(string $reason): void
    {
        $this->logFailure(
            'Endereco: a stored address check result was discarded, ' . $reason . '. The address'
            . ' is treated as never checked and is checked again wherever checks are enabled.'
        );
    }

    /**
     * Logged as an error, because the shop's default log level shows errors
     * only. At most one entry per request.
     */
    private function logFailure(string $message): void
    {
        if ($this->failureLogged) {
            return;
        }
        $this->failureLogged = true;

        try {
            Shop::Container()->getLogService()->error($message);
        } catch (Throwable) {
            // Logging is best effort, a failure must not break the checkout.
        }
    }
}
