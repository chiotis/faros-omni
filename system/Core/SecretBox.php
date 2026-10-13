<?php

declare(strict_types=1);

namespace Omni\Core;

use RuntimeException;

/**
 * Keeps the sites' secrets encrypted in the database (libsodium secretbox). Omni needs a secret itself to check a
 * signature, so it cannot keep only a hash; the key lives in a file outside the web root, made on first use.
 */
final class SecretBox
{
    private ?string $key = null;

    public function __construct(private string $keyPath)
    {
    }

    /** A new secret for a site: 32 random bytes, base64url without padding (43 characters). */
    public static function newSecret(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $this->key()));
    }

    /** The secret, or null when it cannot be read (another key, or damaged). */
    public function decrypt(string $sealed): ?string
    {
        $raw = base64_decode($sealed, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $this->key());
        return $plain === false ? null : $plain;
    }

    /** Makes the key now if there is none (at setup), so it never has to be made while sites connect. */
    public function ensureKey(): void
    {
        $this->key();
    }

    private function key(): string
    {
        if ($this->key !== null) {
            return $this->key;
        }
        if (is_file($this->keyPath)) {
            $key = base64_decode(trim((string)file_get_contents($this->keyPath)), true);
            if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                throw new RuntimeException('The key file ' . $this->keyPath . ' is not a valid key.');
            }
            return $this->key = $key;
        }
        $key = sodium_crypto_secretbox_keygen();
        $dir = dirname($this->keyPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        // Written only if no other request made it first, so two requests never end up with different keys.
        $handle = @fopen($this->keyPath, 'x');
        if ($handle === false) {
            return $this->key();
        }
        fwrite($handle, base64_encode($key) . "\n");
        fclose($handle);
        @chmod($this->keyPath, 0600);
        return $this->key = $key;
    }
}
