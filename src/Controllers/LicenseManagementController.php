<?php

namespace Hearth\LicenseClient\Controllers;

use Hearth\LicenseClient\Encryption;
use Hearth\LicenseClient\LicenseState;
use Hearth\LicenseClient\Package;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;

class LicenseManagementController extends Controller
{
    /**
     * Date comune pentru pagina /licenta.
     *
     * @return array{
     *   license: ?array,
     *   error: ?string,
     *   isValid: bool,
     *   validUntil: ?string,
     *   enforcement: array{ok: bool, code: string},
     *   siteHost: string,
     *   siteUrl: string,
     *   fingerprintSummary: ?string,
     *   licenseRequestEmail: ?string
     * }
     */
    protected function licensePageData(): array
    {
        $path = storage_path('license.json');
        $license = null;
        $error = null;
        $isValid = false;
        $validUntil = null;
        $appUrl = (string) (config('app.url') ?: env('APP_URL', ''));
        $siteHost = (string) (parse_url($appUrl, PHP_URL_HOST) ?: gethostname());
        $fingerprintSummary = $this->readFingerprintSummary();
        $licenseRequestEmail = $this->normalizeRequestEmail(env('LICENSE_REQUEST_EMAIL'));

        if (! file_exists($path)) {
            return [
                'license' => null,
                'error' => null,
                'isValid' => false,
                'validUntil' => null,
                'enforcement' => LicenseState::resolve(),
                'siteHost' => $siteHost,
                'siteUrl' => $appUrl,
                'fingerprintSummary' => $fingerprintSummary,
                'licenseRequestEmail' => $licenseRequestEmail,
            ];
        }

        try {
            $raw = file_get_contents($path);
            $wrapper = json_decode($raw, true);
            if (! is_array($wrapper) || empty($wrapper['payload'])) {
                $error = 'Fișierul de licență este corupt sau are un format neașteptat.';
            } else {
                $decrypted = Encryption::decryptString($wrapper['payload']);
                $license = json_decode($decrypted, true);
                $data = $license['data'] ?? [];
                if (! empty($data)) {
                    if (! empty($data['valid'])) {
                        $isValid = true;
                    }

                    foreach (['expires_at', 'valid_until', 'expiry', 'expires', 'valid_until_at'] as $k) {
                        if (! empty($data[$k])) {
                            try {
                                $dt = new \DateTime($data[$k]);
                                $validUntil = $dt->format(DATE_ATOM);
                                if (empty($isValid)) {
                                    $isValid = (new \DateTime()) < $dt;
                                }
                                break;
                            } catch (\Throwable $e) {
                                // ignorăm erorile de parsare
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $error = 'Eroare la citirea licenței: ' . $e->getMessage();
        }

        return [
            'license' => $license,
            'error' => $error,
            'isValid' => $isValid,
            'validUntil' => $validUntil,
            'enforcement' => LicenseState::resolve(),
            'siteHost' => $siteHost,
            'siteUrl' => $appUrl,
            'fingerprintSummary' => $fingerprintSummary,
            'licenseRequestEmail' => $licenseRequestEmail,
        ];
    }

    protected function readFingerprintSummary(): ?string
    {
        $fpPath = storage_path(Package::fingerprintFile());
        if (! is_file($fpPath)) {
            return null;
        }

        try {
            $raw = file_get_contents($fpPath);
            $j = json_decode((string) $raw, true);
            if (! is_array($j) || empty($j['fingerprint'])) {
                return null;
            }
            $fp = (string) $j['fingerprint'];
            if (strlen($fp) > 48) {
                return substr($fp, 0, 24) . '…' . substr($fp, -12);
            }

            return $fp;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function normalizeRequestEmail(?string $email): ?string
    {
        $email = $email !== null ? trim($email) : '';
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $email;
    }

    /**
     * GET /licenta — interfață principală (Bootstrap 5).
     */
    public function licenta()
    {
        return view('license-client::licenta.index', $this->licensePageData());
    }

    /**
     * POST /licenta/activate — instalare licență din interfață.
     */
    public function upload(Request $request)
    {
        $request->validate(['license_key' => 'required|string']);
        $key = trim($request->input('license_key'));
        $existingPath = storage_path('license.json');

        if (file_exists($existingPath)) {
            try {
                $raw = file_get_contents($existingPath);
                $wrapper = json_decode($raw, true);
                if (is_array($wrapper) && ! empty($wrapper['payload'])) {
                    $decrypted = Encryption::decryptString($wrapper['payload']);
                    $existing = json_decode($decrypted, true);
                    $existingValid = $existing['data']['valid'] ?? false;
                    if ($existingValid && LicenseState::resolve()['ok']) {
                        return redirect()->route('license-client.licenta.index')
                            ->with('license_error', 'O licență validă este deja instalată și nu poate fi suprascrisă. Ștergeți-o manual mai întâi.');
                    }
                }
            } catch (\Throwable $e) {
                // dacă fișierul este corupt, permitem suprascrierea
            }
        }

        $authority = Package::authorityUrl();
        if (empty($authority)) {
            return redirect()->route('license-client.licenta.index')
                ->with('license_error', 'Autoritatea nu este configurată; nu se poate verifica licența online.');
        }

        $verifyPath = Package::verifyEndpoint();
        $verifyUrl = rtrim($authority, '/') . '/' . ltrim($verifyPath, '/');

        try {
            $resp = Http::timeout(Package::remoteTimeout())
                ->post($verifyUrl, [
                    'license_key' => $key,
                    'domain' => parse_url(config('app.url') ?? env('APP_URL', ''), PHP_URL_HOST) ?: gethostname(),
                ]);
            $json = $resp->json();

            if (empty($json['data']) || ! isset($json['signature'])) {
                return redirect()->route('license-client.licenta.index')->with('license_error', 'Răspuns invalid de la autoritate (așteptat data+signature).');
            }

            $data = $json['data'];
            $signature = base64_decode($json['signature']);

            $pemPath = Package::pemEndpoint();
            try {
                $pemResp = Http::timeout(Package::remoteTimeout())->get(rtrim($authority, '/') . '/' . ltrim($pemPath, '/'));
                if (! $pemResp->successful()) {
                    return redirect()->route('license-client.licenta.index')->with('license_error', 'Nu am putut prelua cheia publică de la autoritate: HTTP ' . $pemResp->status());
                }
                $pem = $pemResp->body();
            } catch (\Throwable $e) {
                return redirect()->route('license-client.licenta.index')->with('license_error', 'Eroare la descărcarea cheii publice: ' . $e->getMessage());
            }

            $payloadJson = json_encode($data);
            $pub = openssl_pkey_get_public($pem);
            if ($pub === false) {
                return redirect()->route('license-client.licenta.index')->with('license_error', 'Cheia publică primită de la autoritate este invalidă.');
            }

            $ok = openssl_verify($payloadJson, $signature, $pub, OPENSSL_ALGO_SHA256) === 1;
            openssl_free_key($pub);

            if (! $ok) {
                return redirect()->route('license-client.licenta.index')->with('license_error', 'Verificarea semnăturii a eșuat.');
            }

            $payload = [
                'license_key' => $key,
                'domain' => parse_url(config('app.url') ?? env('APP_URL', ''), PHP_URL_HOST) ?: gethostname(),
                'data' => $data,
                'fetched_at' => now()->toIso8601String(),
                'authority' => $authority,
            ];

            try {
                $plaintext = json_encode($payload, JSON_UNESCAPED_SLASHES);
                $encrypted = Encryption::encryptString($plaintext);
                $wrapper = json_encode([
                    'encrypted' => true,
                    'version' => 1,
                    'payload' => $encrypted,
                ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                file_put_contents($existingPath, $wrapper);
                $serverMessage = $data['message'] ?? null;
                $msg = 'Licența a fost verificată și salvată local.' . ($serverMessage ? ' Mesaj server: ' . $serverMessage : '');

                return redirect()->route('license-client.licenta.index')->with('license_success', $msg);
            } catch (\Throwable $e) {
                return redirect()->route('license-client.licenta.index')->with('license_error', 'Eroare la salvarea licenței: ' . $e->getMessage());
            }
        } catch (\Throwable $e) {
            return redirect()->route('license-client.licenta.index')->with('license_error', 'Eroare la verificarea la autoritate: ' . $e->getMessage());
        }

        return redirect()->route('license-client.licenta.index')
            ->with('license_error', 'Autoritatea a răspuns că licența nu este validă.');
    }

    /**
     * DELETE /licenta — șterge fișierul local de licență.
     */
    public function destroy()
    {
        $path = storage_path('license.json');
        if (! file_exists($path)) {
            return redirect()->route('license-client.licenta.index')
                ->with('license_error', 'Nu există fișier de licență de șters.');
        }

        try {
            $raw = file_get_contents($path);
            $wrapper = json_decode($raw, true);
            if (is_array($wrapper) && ! empty($wrapper['payload'])) {
                try {
                    $decrypted = Encryption::decryptString($wrapper['payload']);
                    $existing = json_decode($decrypted, true);
                    if (! empty($existing['data']['valid']) && LicenseState::resolve()['ok']) {
                        return redirect()->route('license-client.licenta.index')
                            ->with('license_error', 'Licența este validă și nu poate fi ștearsă din interfață.');
                    }
                } catch (\Throwable $e) {
                    // dacă decriptarea eșuează, continuăm
                }
            }

            unlink($path);

            return redirect()->route('license-client.licenta.index')->with('license_success', 'Fișierul de licență a fost șters.');
        } catch (\Throwable $e) {
            return redirect()->route('license-client.licenta.index')
                ->with('license_error', 'Eroare la ștergere: ' . $e->getMessage());
        }
    }

    /**
     * POST /licenta/verify — verifică licența locală cu autoritatea.
     */
    public function verify(Request $request)
    {
        $path = storage_path('license.json');
        if (! file_exists($path)) {
            return redirect()->route('license-client.licenta.index')
                ->with('license_error', 'Nu există nicio licență instalată.');
        }

        try {
            $raw = file_get_contents($path);
            $wrapper = json_decode($raw, true);
            if (! is_array($wrapper) || empty($wrapper['payload'])) {
                return redirect()->route('license-client.licenta.index')
                    ->with('license_error', 'Fișierul de licență este corupt.');
            }

            $decrypted = Encryption::decryptString($wrapper['payload']);
            $license = json_decode($decrypted, true);
            $key = $license['license_key'] ?? null;
            $authority = Package::authorityUrl();

            if (empty($authority) || empty($key)) {
                return redirect()->route('license-client.licenta.index')
                    ->with('license_error', 'Configurare invalidă: lipsește endpointul sau cheia.');
            }

            $verifyPath = Package::verifyEndpoint();
            $verifyUrl = rtrim($authority, '/') . '/' . ltrim($verifyPath, '/');
            $resp = Http::timeout(Package::remoteTimeout())
                ->post($verifyUrl, [
                    'license_key' => $key,
                    'domain' => $license['domain'] ?? null,
                ]);

            if (! $resp->successful()) {
                return redirect()->route('license-client.licenta.index')
                    ->with('license_error', 'Verificare eșuată (HTTP ' . $resp->status() . ').');
            }

            $json = $resp->json();
            if (empty($json['data']) || empty($json['signature'])) {
                return redirect()->route('license-client.licenta.index')
                    ->with('license_error', 'Răspuns invalid de la autoritate (lipsă data+signature).');
            }

            $data = $json['data'];
            $signature = base64_decode($json['signature']);
            $pemPath = Package::pemEndpoint();
            $pemResp = Http::timeout(Package::remoteTimeout())
                ->get(rtrim($authority, '/') . '/' . ltrim($pemPath, '/'));

            if (! $pemResp->successful()) {
                return redirect()->route('license-client.licenta.index')
                    ->with('license_error', 'Nu am putut prelua cheia publică de la autoritate.');
            }

            $pub = openssl_pkey_get_public($pemResp->body());
            if (! $pub) {
                return redirect()->route('license-client.licenta.index')
                    ->with('license_error', 'Cheia publică primită este invalidă.');
            }

            $ok = openssl_verify(json_encode($data), $signature, $pub, OPENSSL_ALGO_SHA256) === 1;
            openssl_free_key($pub);

            if (! $ok) {
                return redirect()->route('license-client.licenta.index')
                    ->with('license_error', 'Semnătura autorității nu este validă.');
            }

            $license['data'] = $data;
            $license['fetched_at'] = now()->toIso8601String();

            $plaintext = json_encode($license, JSON_UNESCAPED_SLASHES);
            $encrypted = Encryption::encryptString($plaintext);
            $wrapper = json_encode(['encrypted' => true, 'version' => 1, 'payload' => $encrypted], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            file_put_contents($path, $wrapper);

            return redirect()->route('license-client.licenta.index')
                ->with('license_success', 'Licența a fost verificată și actualizată cu succes.');
        } catch (\Throwable $e) {
            return redirect()->route('license-client.licenta.index')
                ->with('license_error', 'Eroare la verificare: ' . $e->getMessage());
        }
    }
}
