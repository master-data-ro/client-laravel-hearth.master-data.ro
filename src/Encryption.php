<?php

namespace Hearth\LicenseClient;

class Encryption
{
    // Derive a 32-byte key from APP_KEY or provided passphrase
    public static function deriveKey(?string $passphrase = null): string
    {
        $appKey = $passphrase ?? (getenv('APP_KEY') ?: null);
        if (empty($appKey)) {
            // fallback to a predictable but not ideal key (should prompt in real use)
            $appKey = 'fallback-insecure-key-change-me';
        }

        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7));
            if ($decoded !== false && strlen($decoded) >= 32) {
                return substr($decoded, 0, 32);
            }
            $appKey = $decoded ?: $appKey;
        }

        // Use a SHA-256 hash to ensure 32 bytes
        return hash('sha256', $appKey, true);
    }

    public static function encryptString(string $plaintext, ?string $passphrase = null): string
    {
        $key = self::deriveKey($passphrase);
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed');
        }
        // store base64(iv . ciphertext)
        return base64_encode($iv . $ciphertext);
    }

    public static function decryptString(string $payload, ?string $passphrase = null): string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < 17) {
            throw new \RuntimeException('Invalid encrypted payload');
        }
        $iv = substr($raw, 0, 16);
        $ciphertext = substr($raw, 16);
        $key = self::deriveKey($passphrase);
        $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed');
        }
        return $plaintext;
    }
}
