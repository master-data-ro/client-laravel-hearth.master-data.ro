<?php

namespace Hearth\LicenseClient;

/**
 * Single place for "is there a usable license?" checks (middleware + health probes).
 */
final class LicenseState
{
    public const CODE_MISSING = 'missing';

    public const CODE_INVALID = 'invalid';

    public const CODE_NOT_ACTIVE = 'not_active';

    public const CODE_EXPIRED = 'expired';

    public const CODE_DOMAIN_MISMATCH = 'domain_mismatch';

    public const CODE_OK = 'ok';

    /**
     * @return array{ok: bool, code: string}
     */
    public static function resolve(): array
    {
        $store = storage_path('license.json');
        if (! file_exists($store)) {
            return ['ok' => false, 'code' => self::CODE_MISSING];
        }

        $raw = file_get_contents($store);
        if ($raw === false || $raw === '') {
            return ['ok' => false, 'code' => self::CODE_INVALID];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || empty($decoded['payload']) || empty($decoded['encrypted'])) {
            return ['ok' => false, 'code' => self::CODE_INVALID];
        }

        $obj = null;
        $candidates = [null];
        $candidates = array_values(array_unique(array_filter($candidates, function ($v) {
            return $v !== '';
        })));
        foreach ($candidates as $candidate) {
            try {
                $plaintext = Encryption::decryptString($decoded['payload'], $candidate);
                $maybe = json_decode($plaintext, true);
                if (is_array($maybe) && ! empty($maybe['license_key'])) {
                    $obj = $maybe;
                    break;
                }
            } catch (\Throwable $e) {
                // try next
            }
        }

        if (empty($obj) || empty($obj['license_key'])) {
            return ['ok' => false, 'code' => self::CODE_INVALID];
        }

        $validFlag = $obj['data']['valid'] ?? null;
        if ($validFlag !== true) {
            return ['ok' => false, 'code' => self::CODE_NOT_ACTIVE];
        }

        $expires = $obj['data']['expires_at'] ?? $obj['data']['expires'] ?? null;
        if ($expires) {
            try {
                $expTs = strtotime($expires);
                if ($expTs !== false && $expTs < time()) {
                    return ['ok' => false, 'code' => self::CODE_EXPIRED];
                }
            } catch (\Throwable $e) {
                // allow if not parseable
            }
        }

        $appUrl = config('app.url') ?? env('APP_URL', '');
        $host = parse_url($appUrl, PHP_URL_HOST) ?: gethostname();
        if (! empty($obj['domain']) && $obj['domain'] !== $host) {
            return ['ok' => false, 'code' => self::CODE_DOMAIN_MISMATCH];
        }

        return ['ok' => true, 'code' => self::CODE_OK];
    }

    /**
     * @return array{valid: bool, code: string}
     */
    public static function healthPayload(): array
    {
        $s = self::resolve();

        return [
            'valid' => $s['ok'],
            'code' => $s['code'],
        ];
    }
}
