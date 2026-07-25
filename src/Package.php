<?php

namespace Hearth\LicenseClient;

/**
 * Internal, non-overridable package configuration.
 * Clients cannot change these values; only the authority can update the package.
 */
final class Package
{
    /** Header pe răspunsuri 403 JSON (blocare licență) — contract stabil pentru SPA. */
    public const HEADER_LICENSE_CODE = 'X-License-Code';

    public const HEADER_LICENSE_OK = 'X-License-Ok';

    private const AUTHORITY_URL = 'https://hearth.scmc.ro';
    private const AUTHORITY_URL_FALLBACK = 'https://hearth.master-data.ro';
    private const VERIFY_ENDPOINT = '/api/verify';
    private const PEM_ENDPOINT = '/keys/pem';
    private const ALERT_ENDPOINT = '/api/alert/fraud';

    private const REMOTE_TIMEOUT = 5;

    private const GLOBAL_ENFORCE = true;

    private const FINGERPRINT_FILE = 'license-fingerprint.json';

    private const AUTHORITY_PRIVATE_KEY_STORAGE = 'keys/private.pem';

    private const WHITELIST = [
        '/health',
        '/.well-known/push-license',
        '/.well-known/jwks.json',
        '/keys/pem',
        '/setari',
    ];

    public static function authorityUrl(): string
    {
        $fromConfig = function_exists('config') ? config('scmc_integration.license_authority_url') : null;
        if (is_string($fromConfig) && $fromConfig !== '') {
            return rtrim($fromConfig, '/');
        }

        return self::AUTHORITY_URL;
    }

    public static function authorityUrlFallback(): string
    {
        $fromConfig = function_exists('config') ? config('scmc_integration.license_authority_url_fallback') : null;
        if (is_string($fromConfig) && $fromConfig !== '') {
            return rtrim($fromConfig, '/');
        }

        return self::AUTHORITY_URL_FALLBACK;
    }

    public static function legacyFallbackEnabled(): bool
    {
        if (function_exists('config')) {
            return filter_var(config('scmc_integration.legacy_fallback_enabled', true), FILTER_VALIDATE_BOOLEAN);
        }

        $env = getenv('SCMC_INTEGRATION_LEGACY_FALLBACK_ENABLED');

        return filter_var($env === false ? 'true' : $env, FILTER_VALIDATE_BOOLEAN);
    }

    public static function verifyEndpoint(): string
    {
        return self::VERIFY_ENDPOINT;
    }

    public static function pemEndpoint(): string
    {
        return self::PEM_ENDPOINT;
    }

    public static function alertEndpoint(): string
    {
        return self::ALERT_ENDPOINT;
    }

    public static function remoteTimeout(): int
    {
        return self::REMOTE_TIMEOUT;
    }

    public static function globalEnforce(): bool
    {
        return self::GLOBAL_ENFORCE;
    }

    public static function authoritySigningPrivateKeyPath(): string
    {
        return storage_path(self::AUTHORITY_PRIVATE_KEY_STORAGE);
    }

    public static function fingerprintFile(): string
    {
        return self::FINGERPRINT_FILE;
    }

    public static function whitelist(): array
    {
        return self::WHITELIST;
    }

    public static function appUrlPathPrefix(): string
    {
        $url = (string) (config('app.url') ?? env('APP_URL', ''));
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '' || $path === '/') {
            return '';
        }

        return '/'.trim($path, '/');
    }

    public static function licenseActivationBasePath(): string
    {
        return self::appUrlPathPrefix().'/licenta';
    }

    public static function isLicenseActivationPath(string $path): bool
    {
        $base = self::licenseActivationBasePath();

        return $path === $base || str_starts_with($path, $base.'/');
    }

    /**
     * @return array{message: string, license_code: string, retry_after?: int}
     */
    public static function licenseForbiddenJsonBody(string $message, string $licenseCode, ?int $retryAfter = null): array
    {
        $body = [
            'message' => $message,
            'license_code' => $licenseCode,
        ];
        if ($retryAfter !== null) {
            $body['retry_after'] = $retryAfter;
        }

        return $body;
    }

    /**
     * @deprecated Use authoritySigningPrivateKeyPath()
     */
    public static function privateKeyPath(): ?string
    {
        $p = self::authoritySigningPrivateKeyPath();

        return file_exists($p) ? $p : null;
    }
}
