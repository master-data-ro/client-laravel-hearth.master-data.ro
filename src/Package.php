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
     * Rutele UI pentru activarea licenței (doar /licenta). Accesibile doar când licența nu e validă.
     */
    public static function isLicenseActivationPath(string $path): bool
    {
        return $path === '/licenta' || str_starts_with($path, '/licenta/');
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
