<?php

namespace Hearth\LicenseClient;

use Illuminate\Support\Facades\Http;

/**
 * Verificare la autoritate + salvare locală (folosit de UI, verify și cron).
 */
final class AuthorityLicenseSync
{
    /**
     * Cheia din storage/license.json decryptată, sau null.
     */
    public static function resolveStoredLicenseKey(): ?string
    {
        $path = storage_path('license.json');
        if (! is_file($path)) {
            return null;
        }

        try {
            $raw = file_get_contents($path);
            $wrapper = json_decode($raw, true);
            if (! is_array($wrapper) || empty($wrapper['payload'])) {
                return null;
            }
            $decrypted = Encryption::decryptString($wrapper['payload']);
            $license = json_decode($decrypted, true);
            $key = $license['license_key'] ?? null;

            return is_string($key) && $key !== '' ? $key : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Instalare tip autoritate: nu încercăm sync remote către noi înșine.
     */
    public static function shouldSkipRemoteSync(): bool
    {
        if (is_file(Package::authoritySigningPrivateKeyPath())) {
            return true;
        }

        $authority = Package::authorityUrl();
        $authHost = parse_url(rtrim($authority, '/'), PHP_URL_HOST) ?: null;
        $appUrl = config('app.url') ?? env('APP_URL', '');
        $appHost = parse_url((string) $appUrl, PHP_URL_HOST) ?: null;

        return $authHost && $appHost && strcasecmp((string) $authHost, (string) $appHost) === 0;
    }

    /**
     * @return array{ok: bool, error?: string, success?: string}
     */
    public static function sync(string $key, bool $skipActiveLocalOverwriteGuard, ?string $successPrefix): array
    {
        if (self::shouldSkipRemoteSync()) {
            return ['ok' => true, 'success' => 'Sărit (mod autoritate / același host ca autoritatea).'];
        }

        $existingPath = storage_path('license.json');

        if (! $skipActiveLocalOverwriteGuard && file_exists($existingPath)) {
            try {
                $raw = file_get_contents($existingPath);
                $wrapper = json_decode($raw, true);
                if (is_array($wrapper) && ! empty($wrapper['payload'])) {
                    $decrypted = Encryption::decryptString($wrapper['payload']);
                    $existing = json_decode($decrypted, true);
                    $existingValid = $existing['data']['valid'] ?? false;
                    if ($existingValid && LicenseState::resolve()['ok']) {
                        return ['ok' => false, 'error' => 'O licență validă este deja instalată și nu poate fi suprascrisă din acest flux.'];
                    }
                }
            } catch (\Throwable $e) {
                // fișier corupt: continuăm
            }
        }

        $authority = Package::authorityUrl();
        if ($authority === '') {
            return ['ok' => false, 'error' => 'Autoritatea nu este configurată.'];
        }

        $verifyUrl = rtrim($authority, '/') . '/' . ltrim(Package::verifyEndpoint(), '/');

        try {
            $resp = Http::timeout(Package::remoteTimeout())
                ->post($verifyUrl, [
                    'license_key' => $key,
                    'domain' => parse_url(config('app.url') ?? env('APP_URL', ''), PHP_URL_HOST) ?: gethostname(),
                ]);
            $json = $resp->json();

            if (empty($json['data']) || ! isset($json['signature'])) {
                return ['ok' => false, 'error' => 'Răspuns invalid de la autoritate (așteptat data+signature).'];
            }

            $data = $json['data'];
            $signature = base64_decode($json['signature']);

            try {
                $pemResp = Http::timeout(Package::remoteTimeout())
                    ->get(rtrim($authority, '/') . '/' . ltrim(Package::pemEndpoint(), '/'));
                if (! $pemResp->successful()) {
                    return ['ok' => false, 'error' => 'Nu am putut prelua cheia publică de la autoritate: HTTP ' . $pemResp->status()];
                }
                $pem = $pemResp->body();
            } catch (\Throwable $e) {
                return ['ok' => false, 'error' => 'Eroare la descărcarea cheii publice: ' . $e->getMessage()];
            }

            $payloadJson = json_encode($data);
            $pub = openssl_pkey_get_public($pem);
            if ($pub === false) {
                return ['ok' => false, 'error' => 'Cheia publică primită de la autoritate este invalidă.'];
            }

            $ok = openssl_verify($payloadJson, $signature, $pub, OPENSSL_ALGO_SHA256) === 1;

            if (! $ok) {
                return ['ok' => false, 'error' => 'Verificarea semnăturii a eșuat.'];
            }

            $payload = [
                'license_key' => $key,
                'domain' => parse_url(config('app.url') ?? env('APP_URL', ''), PHP_URL_HOST) ?: gethostname(),
                'data' => $data,
                'fetched_at' => now()->toIso8601String(),
                'authority' => $authority,
            ];

            $plaintext = json_encode($payload, JSON_UNESCAPED_SLASHES);
            $encrypted = Encryption::encryptString($plaintext);
            $wrapper = json_encode([
                'encrypted' => true,
                'version' => 1,
                'payload' => $encrypted,
            ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            file_put_contents($existingPath, $wrapper);

            $serverMessage = $data['message'] ?? null;
            $base = 'Licența a fost verificată și salvată local.';
            if ($successPrefix !== null && $successPrefix !== '') {
                $base = trim($successPrefix) . ' ' . $base;
            }
            $msg = $base . ($serverMessage ? ' Mesaj server: ' . $serverMessage : '');

            return ['ok' => true, 'success' => $msg];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Eroare la verificarea la autoritate: ' . $e->getMessage()];
        }
    }
}
