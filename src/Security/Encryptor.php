<?php

declare(strict_types=1);

namespace NeneClear\Security;

use InvalidArgumentException;
use RuntimeException;

/**
 * Application-level authenticated encryption for sensitive fields at rest
 * (libsodium secretbox). Stored ciphertext is prefixed `enc:v1:` so reads can
 * tell encrypted values from legacy plaintext and decrypt only the former —
 * making a partial rollout and lazy re-encryption (on the next write) safe.
 *
 * When no key is configured the encryptor is a **passthrough**: `encrypt()`
 * returns the plaintext unchanged and `decrypt()` returns stored values as-is.
 * This keeps existing deployments working; set `NENE_CLEAR_ENCRYPTION_KEY`
 * (base64 of 32 random bytes) to turn encryption on. Do **not** lose the key —
 * values written while it was set cannot be read without it.
 */
final readonly class Encryptor
{
    private const string PREFIX = 'enc:v1:';

    private ?string $key;

    public function __construct(?string $base64Key = null)
    {
        if ($base64Key === null || $base64Key === '') {
            $this->key = null;

            return;
        }

        $key = base64_decode($base64Key, true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new InvalidArgumentException(sprintf(
                'NENE_CLEAR_ENCRYPTION_KEY must be base64 of exactly %d bytes.',
                SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
            ));
        }

        $this->key = $key;
    }

    public function isEnabled(): bool
    {
        return $this->key !== null;
    }

    public function encrypt(string $plaintext): string
    {
        if ($this->key === null) {
            return $plaintext;
        }

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, $this->key);

        return self::PREFIX . base64_encode($nonce . $cipher);
    }

    public function decrypt(string $stored): string
    {
        if (!str_starts_with($stored, self::PREFIX)) {
            return $stored; // legacy plaintext, or encryption was off at write time
        }

        if ($this->key === null) {
            throw new RuntimeException('Encrypted value found but no encryption key is configured.');
        }

        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Corrupt encrypted value.');
        }

        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->key);
        if ($plain === false) {
            throw new RuntimeException('Decryption failed (wrong key or tampered data).');
        }

        return $plain;
    }
}
