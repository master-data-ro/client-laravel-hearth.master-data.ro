<?php

namespace Hearth\LicenseClient\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Hearth\LicenseClient\Encryption;

class PushLicenseController extends Controller
{
    /**
     * Receive a pushed, signed license payload from the authority.
     * Expected JSON: { "payload": "{...}", "signature": "base64...", "kid": "optional" }
     */
    public function receive(Request $request)
    {
        $payload = $request->input('payload');
        $signature = $request->input('signature');

        if (empty($payload) || empty($signature)) {
            return response()->json(['error' => 'payload and signature required'], 400);
        }

        $pubPath = __DIR__ . '/../../keys/public.pem';
        if (!file_exists($pubPath)) {
            return response()->json(['error' => 'server public key not available'], 500);
        }

        $kid = $request->input('kid');

        // Determine which public key to use: if kid present, try to fetch key from authority JWKS.
        $pubKeyPem = null;
        if (!empty($kid)) {
            $authority = config('license-client.authority_url') ?? null;
            if ($authority) {
                try {
                    $jwksResp = \Illuminate\Support\Facades\Http::timeout(config('license-client.remote_timeout', 5))->get(rtrim($authority, '/') . '/.well-known/jwks.json');
                    if ($jwksResp->successful()) {
                        $jwks = $jwksResp->json();
                        foreach ($jwks['keys'] ?? [] as $jwk) {
                            if (!empty($jwk['kid']) && $jwk['kid'] === $kid) {
                                // Prefer x5c certificate if present
                                if (!empty($jwk['x5c'][0])) {
                                    $cert = chunk_split($jwk['x5c'][0], 64, "\n");
                                    $certPem = "-----BEGIN CERTIFICATE-----\n" . $cert . "-----END CERTIFICATE-----\n";
                                    $pubKeyPem = openssl_pkey_get_public($certPem) ? $certPem : null;
                                }
                                // If no x5c, we may fallback to using the bundled public key only if n/e matches
                                if (empty($pubKeyPem) && !empty($jwk['n']) && !empty($jwk['e'])) {
                                    // compare to bundled public key
                                    $localPem = file_exists($pubPath) ? file_get_contents($pubPath) : null;
                                    if ($localPem) {
                                        $res = openssl_pkey_get_public($localPem);
                                        $details = $res ? openssl_pkey_get_details($res) : null;
                                        if ($details && !empty($details['rsa']['n']) && !empty($details['rsa']['e'])) {
                                            $localN = rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '=');
                                            $localE = rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '=');
                                            if (hash_equals($localN, $jwk['n']) && hash_equals($localE, $jwk['e'])) {
                                                $pubKeyPem = $localPem;
                                            }
                                        }
                                    }
                                }
                                break;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore jwks fetch errors and fallback to bundled key below
                }
            }
        }

        // Fallback: use bundled public.pem
        if (empty($pubKeyPem)) {
            if (!file_exists($pubPath)) {
                return response()->json(['error' => 'server public key not available'], 500);
            }
            $pubKeyPem = file_get_contents($pubPath);
        }

        $sigRaw = base64_decode($signature, true);
        if ($sigRaw === false) {
            return response()->json(['error' => 'invalid signature encoding'], 400);
        }

        $pub = openssl_pkey_get_public($pubKeyPem);
        if ($pub === false) {
            return response()->json(['error' => 'invalid public key'], 500);
        }

        $verified = openssl_verify($payload, $sigRaw, $pub, OPENSSL_ALGO_SHA256) === 1;
        openssl_free_key($pub);

        if (! $verified) {
            return response()->json(['error' => 'signature verification failed'], 403);
        }

        $decoded = json_decode($payload, true);
        if (!is_array($decoded) || empty($decoded['domain']) || empty($decoded['license_key'])) {
            return response()->json(['error' => 'invalid payload format'], 400);
        }

        // Ensure payload domain matches this app host
        $appUrl = config('app.url') ?? env('APP_URL', '');
        $host = parse_url($appUrl, PHP_URL_HOST) ?: gethostname();
        if ($decoded['domain'] !== $host) {
            return response()->json(['error' => 'domain mismatch'], 403);
        }

        // Prepare wrapper and write encrypted storage file
        try {
            $plaintext = json_encode([
                'license_key' => $decoded['license_key'],
                'domain' => $decoded['domain'],
                'data' => $decoded['data'] ?? [],
                'fetched_at' => now()->toIso8601String(),
                'authority' => config('license-client.authority_url') ?? null,
            ], JSON_UNESCAPED_SLASHES);

            $encrypted = Encryption::encryptString($plaintext);
            $wrapper = json_encode([
                'encrypted' => true,
                'version' => 1,
                'payload' => $encrypted,
            ], JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);

            file_put_contents(storage_path('license.json'), $wrapper);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'failed to save license', 'detail' => $e->getMessage()], 500);
        }

        return response()->json(['ok' => true], 200);
    }
}
