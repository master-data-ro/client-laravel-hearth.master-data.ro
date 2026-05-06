<?php

namespace Hearth\LicenseClient;

/**
 * Internal, non-overridable package configuration.
 * Clients cannot change these values; only the authority can update the package.
 */
final class Package
{
    // Fixed authority and endpoints
    private const AUTHORITY_URL = 'https://hearth.master-data.ro';
    private const VERIFY_ENDPOINT = '/api/verify';
    private const PEM_ENDPOINT = '/keys/pem';
    private const ALERT_ENDPOINT = '/api/alert/fraud';

    // Networking
    private const REMOTE_TIMEOUT = 5; // seconds

    // Enforcement
    private const GLOBAL_ENFORCE = true; // always prepend to kernel too

    // Files
    private const FINGERPRINT_FILE = 'license-fingerprint.json';

    /** Relative to Laravel storage/ — authority signing key (fixed; not configurable). */
    private const AUTHORITY_PRIVATE_KEY_STORAGE = 'keys/private.pem';

    // Whitelisted paths that bypass enforcement (prefix matches).
    // /licenta nu e aici — e tratată în middleware (doar fără licență validă).
    private const WHITELIST = [
        '/health',
        '/.well-known/push-license',
        '/.well-known/jwks.json',
        '/keys/pem',
        '/setari',
    ];

    public static function authorityUrl(): string
    {
        return self::AUTHORITY_URL;
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

    /**
     * Expected PEM path on authority installs. Clients must not ship this file.
     */
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

    /**
     * Path segment from APP_URL when the app is served under a subdirectory (e.g. /myapp).
     * Returns '' or '/myapp' (no trailing slash).
     */
    public static function appUrlPathPrefix(): string
    {
        $url = (string) (config('app.url') ?? env('APP_URL', ''));
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '' || $path === '/') {
            return '';
        }

        return '/' . trim($path, '/');
    }

    /**
     * Full URI path to the license UI root (e.g. /licenta or /myapp/licenta).
     */
    public static function licenseActivationBasePath(): string
    {
        return self::appUrlPathPrefix() . '/licenta';
    }

    /**
     * Rutele UI pentru activarea licenței (/licenta, eventual cu prefix din APP_URL).
     * Accesibile doar când licența nu e validă.
     */
    public static function isLicenseActivationPath(string $path): bool
    {
        $base = self::licenseActivationBasePath();

        return $path === $base || str_starts_with($path, $base . '/');
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
