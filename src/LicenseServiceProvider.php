<?php

namespace Hearth\LicenseClient;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Http;

class LicenseServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/license.php', 'license');
    }

    /**
     * Verify that the bundled public key corresponds to one of the keys in the
     * authority's JWKS (if the JWKS endpoint is reachable). If the JWKS is
     * reachable but doesn't include our key, we abort to avoid running with a
     * mismatched verification key.
     *
     * @throws \RuntimeException
     */
    protected function verifyBundledKeyAgainstJwks()
    {
        $authority = Package::authorityUrl();
        $jwksUrl = rtrim($authority, '/') . '/.well-known/jwks.json';

        try {
            $resp = Http::timeout(10)->get($jwksUrl);
        } catch (\Throwable $e) {
            // Could not fetch JWKS; allow boot to continue (network issues)
            logger()->warning('Could not fetch JWKS for license-client verification: ' . $e->getMessage());
            return;
        }

        if (! $resp->successful()) {
            logger()->warning('JWKS not available (HTTP ' . $resp->status() . ')');
            return;
        }

        $json = $resp->json();
        if (empty($json['keys']) || !is_array($json['keys'])) {
            logger()->warning('JWKS payload malformed or empty');
            return;
        }

        // Extract local public key n/e in base64url form
        $localPemPath = __DIR__ . '/../keys/public.pem';
        if (!file_exists($localPemPath)) {
            // If the bundle doesn't include a public.pem (e.g., path repository during
            // development), don't abort the boot — log and skip verification.
            logger()->warning('Bundled public.pem not found for license-client; skipping JWKS verification.');
            return;
        }
        $localPem = file_get_contents($localPemPath);
        $res = openssl_pkey_get_public($localPem);
        if ($res === false) {
            logger()->warning('Bundled public.pem is invalid; skipping JWKS verification.');
            return;
        }
        $details = openssl_pkey_get_details($res);
        if (empty($details['rsa']['n']) || empty($details['rsa']['e'])) {
            logger()->warning('Failed to extract RSA parameters from bundled public key; skipping JWKS verification.');
            return;
        }
        $localN = $this->base64UrlEncode($details['rsa']['n']);
        $localE = $this->base64UrlEncode($details['rsa']['e']);

        foreach ($json['keys'] as $jwk) {
            if (!empty($jwk['n']) && !empty($jwk['e'])) {
                if (hash_equals($localN, $jwk['n']) && hash_equals($localE, $jwk['e'])) {
                    // match found
                    return;
                }
            }
        }

        // JWKS reachable but no matching key found -> abort
        throw new \RuntimeException('Bundled public key does not match any key in authority JWKS. Aborting to avoid running with mismatched verification key.');
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Fatal authority misconfiguration must abort boot; benign openssl noise must not.
     */
    protected function shouldRethrowAuthorityDetectionFailure(\Throwable $e): bool
    {
        if (! $e instanceof \RuntimeException) {
            return false;
        }

        $msg = $e->getMessage();

        return str_contains($msg, 'aborting boot')
            || str_contains($msg, 'Private key does not match')
            || str_contains($msg, 'Bundled public key')
            || str_contains($msg, 'Authority JWKS does not include');
    }

    protected function registerLicenseEnforcementMiddleware(): void
    {
        if (config('license.global_enforce')) {
            $this->app->afterResolving(\Illuminate\Contracts\Http\Kernel::class, function (\Illuminate\Contracts\Http\Kernel $kernel) {
                if (method_exists($kernel, 'prependMiddleware')) {
                    $kernel->prependMiddleware(\Hearth\LicenseClient\Middleware\EnsureHasValidLicense::class);
                }
            });

            return;
        }

        $this->app->afterResolving(\Illuminate\Routing\Router::class, function (\Illuminate\Routing\Router $router) {
            $router->pushMiddlewareToGroup('web', \Hearth\LicenseClient\Middleware\EnsureHasValidLicense::class);
        });
    }

    /**
     * Notify the authority about a potential fraud/integrity issue.
     * This is best-effort and will not block boot if the notify fails.
     */
    protected function notifyAuthorityAlert(?string $privatePem, array $alert): void
    {
        $authority = Package::authorityUrl();
        $endpoint = rtrim($authority, '/') . '/api/alert';

        $payloadJson = json_encode($alert, JSON_UNESCAPED_SLASHES);
        $signature = null;

        try {
            if (!empty($privatePem) && @openssl_pkey_get_private($privatePem) !== false) {
                $res = @openssl_pkey_get_private($privatePem);
                openssl_sign($payloadJson, $rawSig, $res, OPENSSL_ALGO_SHA256);
                if (!empty($rawSig)) {
                    $signature = base64_encode($rawSig);
                }
                if (is_resource($res)) {
                    @openssl_free_key($res);
                }
            }
        } catch (\Throwable $e) {
            logger()->warning('Failed to sign authority alert: ' . $e->getMessage());
        }

        try {
            Http::timeout(3)->post($endpoint, ['payload' => $payloadJson, 'signature' => $signature]);
        } catch (\Throwable $e) {
            logger()->warning('Failed to send authority alert HTTP request: ' . $e->getMessage());
        }
    }

    public function boot()
    {
        // Verify that the bundled public key matches the authority JWKS if available.
        try {
            $this->verifyBundledKeyAgainstJwks();
        } catch (\Throwable $e) {
            // If verification fails, abort boot to avoid running with a mismatched key.
            // Throwing here will surface the problem early.
            throw $e;
        }

        // No config publish — all options are internal to the package.

        // Load package views (embedded, not publishable) so blocked page is always available
        if (file_exists(__DIR__ . '/../resources/views')) {
            $this->loadViewsFrom(__DIR__ . '/../resources/views', 'license-client');
        }

        // Load package web routes for interactive license management (/licente)
        // These routes are intentionally minimal and placed inside the package so
        // that the host application does not need to provide an interactive
        // license UI.
        $routesPath = __DIR__ . '/../routes/web.php';
        if (file_exists($routesPath)) {
            $this->loadRoutesFrom($routesPath);
        }
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\MakeLicenseServerCommand::class,
            ]);
            return;
        }

        // Enforce license on every HTTP request unless this app is the authority.
        // global_enforce prepends middleware to the kernel so API routes are covered too.
        $isAuthority = false;

        try {
            $privPath = config('license.private_key_path');
            if (!empty($privPath) && file_exists($privPath)) {
                $priv = @file_get_contents($privPath);
                $privRes = false;
                if (!empty($priv) && ($privRes = @openssl_pkey_get_private($priv)) !== false) {
                    $isAuthority = true;

                    try {
                        $privDetails = openssl_pkey_get_details($privRes);
                        if (!empty($privDetails['rsa']['n']) && !empty($privDetails['rsa']['e'])) {
                            $localN = $this->base64UrlEncode($privDetails['rsa']['n']);
                            $localE = $this->base64UrlEncode($privDetails['rsa']['e']);

                            $bundledPub = __DIR__ . '/../keys/public.pem';
                            if (file_exists($bundledPub)) {
                                $pubPem = @file_get_contents($bundledPub);
                                $pubRes = @openssl_pkey_get_public($pubPem);
                                if ($pubRes !== false) {
                                    $pubDetails = openssl_pkey_get_details($pubRes);
                                    if (empty($pubDetails['rsa']['n']) || empty($pubDetails['rsa']['e'])) {
                                        throw new \RuntimeException('Bundled public key malformed');
                                    }
                                    $bundledN = $this->base64UrlEncode($pubDetails['rsa']['n']);
                                    $bundledE = $this->base64UrlEncode($pubDetails['rsa']['e']);
                                    if (!hash_equals($localN, $bundledN) || !hash_equals($localE, $bundledE)) {
                                        throw new \RuntimeException('Private key does not match bundled public key — aborting boot to protect authority identity.');
                                    }
                                } else {
                                    throw new \RuntimeException('Bundled public key is invalid');
                                }
                            }

                            $authority = Package::authorityUrl();
                            try {
                                $jwksUrl = rtrim($authority, '/') . '/.well-known/jwks.json';
                                $resp = Http::timeout(10)->get($jwksUrl);
                                if ($resp->successful()) {
                                    $json = $resp->json();
                                    $found = false;
                                    foreach ($json['keys'] ?? [] as $jwk) {
                                        if (!empty($jwk['n']) && !empty($jwk['e'])) {
                                            if (hash_equals($localN, $jwk['n']) && hash_equals($localE, $jwk['e'])) {
                                                $found = true;
                                                break;
                                            }
                                        }
                                    }
                                    if (! $found) {
                                        $alert = [
                                            'host' => $this->app->make('config')->get('app.url') ?? env('APP_URL', ''),
                                            'message' => 'Authority JWKS does not include the private key\'s public counterpart',
                                            'time' => now()->toIso8601String(),
                                        ];
                                        try {
                                            $this->notifyAuthorityAlert($priv, $alert);
                                        } catch (\Throwable $_e) {
                                            logger()->warning('Failed to send authority alert: ' . $_e->getMessage());
                                        }
                                        throw new \RuntimeException('Authority JWKS does not include the private key\'s public counterpart — aborting boot.');
                                    }
                                } else {
                                    logger()->warning('Authority JWKS not available during integrity check (HTTP ' . $resp->status() . '), continuing with bundled key validation.');
                                }
                            } catch (\Throwable $e) {
                                logger()->warning('Failed to fetch/parse JWKS during authority integrity check: ' . $e->getMessage());
                            }
                        }
                    } catch (\Throwable $e) {
                        try {
                            $alert = [
                                'host' => $this->app->make('config')->get('app.url') ?? env('APP_URL', ''),
                                'message' => $e->getMessage(),
                                'time' => now()->toIso8601String(),
                            ];
                            $this->notifyAuthorityAlert(isset($priv) ? $priv : null, $alert);
                        } catch (\Throwable $_notify) {
                            logger()->warning('Failed to send authority alert after integrity failure: ' . $_notify->getMessage());
                        }

                        throw $e;
                    } finally {
                        if (is_resource($privRes)) {
                            @openssl_free_key($privRes);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            if ($this->shouldRethrowAuthorityDetectionFailure($e)) {
                throw $e;
            }
            logger()->debug('License client authority detection: ' . $e->getMessage());
        }

        if (! $isAuthority) {
            $authority = Package::authorityUrl();
            $authHost = parse_url(rtrim($authority, '/'), PHP_URL_HOST) ?: null;
            $appUrl = config('app.url') ?? env('APP_URL', '');
            $appHost = parse_url($appUrl, PHP_URL_HOST) ?: null;
            if ($authHost && $appHost && strcasecmp($authHost, $appHost) === 0) {
                $isAuthority = true;
            }
        }

        if ($isAuthority) {
            logger()->info('License client: this application is configured as authority — skipping enforcement middleware.');
        } else {
            $this->registerLicenseEnforcementMiddleware();
        }

        // If we detected a private key and integrity validated, write a
        // signed fingerprint file for future reference.
        try {
            $privPath = config('license.private_key_path');

            if (!empty($privPath) && file_exists($privPath)) {
                $priv = @file_get_contents($privPath);
                if (!empty($priv) && @openssl_pkey_get_private($priv) !== false) {
                    // derive fingerprint from public key
                    $pubPath = __DIR__ . '/../keys/public.pem';
                    $pubPem = null;
                    if (file_exists($pubPath)) {
                        $pubPem = @file_get_contents($pubPath);
                    }
                    $fingerprint = null;
                    if ($pubPem) {
                        $fingerprint = hash('sha256', $pubPem);
                    } else {
                        // fallback: derive public from private
                        $res = @openssl_pkey_get_private($priv);
                        $details = openssl_pkey_get_details($res);
                        $pubPem = null;
                        if (!empty($details['rsa']['n'])) {
                            // best-effort: convert modulus to PEM is non-trivial; use n/e hash
                            $fingerprint = $this->base64UrlEncode($details['rsa']['n']) . '.' . $this->base64UrlEncode($details['rsa']['e']);
                        }
                        if (is_resource($res)) {
                            @openssl_free_key($res);
                        }
                    }

                    if ($fingerprint) {
                        try {
                            $sig = null;
                            $res = @openssl_pkey_get_private($priv);
                            if ($res !== false) {
                                openssl_sign($fingerprint, $rawSig, $res, OPENSSL_ALGO_SHA256);
                                if (!empty($rawSig)) {
                                    $sig = base64_encode($rawSig);
                                }
                                if (is_resource($res)) {
                                    @openssl_free_key($res);
                                }
                            }

                            $wrapper = json_encode(['fingerprint' => $fingerprint, 'signature' => $sig, 'created_at' => now()->toIso8601String()], JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
                            $fpFile = storage_path('license-fingerprint.json');
                            @file_put_contents($fpFile, $wrapper);
                        } catch (\Throwable $e) {
                            logger()->warning('Failed to write signed fingerprint: ' . $e->getMessage());
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            logger()->debug('Fingerprint generation skipped: ' . $e->getMessage());
        }

        // No panic/unlock routes registered - panic functionality removed.

        // Register a simple signed-push endpoint so the authority can POST a
        // signed license payload directly to this client. This is registered
        // on the API middleware group to avoid CSRF requirements.
        // Only register if controller exists
        try {
            $controllerClass = '\\Hearth\\LicenseClient\\Controllers\\PushLicenseController';
            if (class_exists($controllerClass)) {
                \Illuminate\Support\Facades\Route::post('/.well-known/push-license', $controllerClass . '@receive')
                    ->middleware('api');

                // Health-check GET for easier probing of the endpoint
                \Illuminate\Support\Facades\Route::get('/.well-known/push-license', $controllerClass . '@health')
                    ->middleware('api');
            }
        } catch (\Throwable $e) {
            // ignore if route registration fails
        }
    }
}
