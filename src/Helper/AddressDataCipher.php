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

    private CryptoServiceInterface $cryptoService;
    private bool $failureLogged = false;

    public function __construct(CryptoServiceInterface $cryptoService)
    {
        $this->cryptoService = $cryptoService;
    }

    /**
     * @param mixed[] $predictions Predictions in the internal format.
     *
     * @return mixed[] The predictions with their personal fields encrypted. If
     *                 they cannot be encrypted, e.g. because BLOWFISH_KEY is not
     *                 defined, they are returned unchanged: losing checked data
     *                 is worse than storing it readable for a while, and the
     *                 readable values are encrypted once the key is back.
     */
    public function encryptPredictions(array $predictions): array
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
                'Endereco: address predictions could not be encrypted, BLOWFISH_KEY is probably'
                . ' missing. They are stored readable and encrypted once the key is available.'
            );
            return $predictions;
        }

        return $encryptedPredictions;
    }

    /**
     * Values without the prefix are returned unchanged, they were stored before
     * the encryption was introduced or while BLOWFISH_KEY was missing.
     *
     * @param mixed[] $predictions Predictions as stored in the database.
     *
     * @return mixed[]|null The readable predictions, or null if any value cannot
     *                      be decrypted with the current key.
     */
    public function decryptPredictions(array $predictions): ?array
    {
        foreach ($predictions as $index => $prediction) {
            if (!is_array($prediction)) {
                continue;
            }

            foreach (self::ENCRYPTED_FIELDS as $field) {
                $value = $prediction[$field] ?? null;
                if (!is_string($value) || !$this->isEncrypted($value)) {
                    continue;
                }

                $decryptedValue = $this->decryptValue($value);
                if ($decryptedValue === null) {
                    $this->logFailure(
                        'Endereco: stored address predictions could not be decrypted, the BLOWFISH_KEY'
                        . ' has probably changed. The affected addresses count as unchecked until the'
                        . ' customer edits them and the Endereco JS SDK checks them again.'
                    );
                    return null;
                }

                $predictions[$index][$field] = $decryptedValue;
            }
        }

        return $predictions;
    }

    /**
     * @param mixed[] $predictions Predictions as stored in the database.
     *
     * @return bool True if a personal field is still stored readable.
     */
    public function hasUnencryptedValues(array $predictions): bool
    {
        foreach ($predictions as $prediction) {
            if (!is_array($prediction)) {
                continue;
            }

            foreach (self::ENCRYPTED_FIELDS as $field) {
                if ($this->isUnencryptedValue($prediction[$field] ?? null)) {
                    return true;
                }
            }
        }

        return false;
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
        if (!$this->isEncrypted($value)) {
            return null;
        }

        // Checked upfront, because the XTEA implementation reads past the end
        // of a truncated cipher text instead of failing.
        $cipherText = substr($value, strlen(self::PREFIX));
        $rawCipherText = base64_decode($cipherText, true);
        if ($rawCipherText === false || strlen($rawCipherText) < 16 || strlen($rawCipherText) % 8 !== 0) {
            return null;
        }

        try {
            // XTEA pads the plain text with spaces to full blocks.
            $plainText = rtrim($this->cryptoService->decryptXTEA($cipherText), ' ');
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

    private function logFailure(string $message): void
    {
        if ($this->failureLogged) {
            return;
        }
        $this->failureLogged = true;

        try {
            Shop::Container()->getLogService()->warning($message);
        } catch (Throwable) {
            // Logging is best effort, a failure must not break the checkout.
        }
    }
}
