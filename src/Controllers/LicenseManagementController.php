<?php

namespace Hearth\LicenseClient\Controllers;

use Hearth\LicenseClient\AuthorityLicenseSync;
use Hearth\LicenseClient\Encryption;
use Hearth\LicenseClient\LicenseState;
use Hearth\LicenseClient\Package;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

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
     *   fingerprintSummary: ?string
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
        ];
    }

    /**
     * Cheia salvată local (decrypt), sau null dacă nu există / e coruptă.
     */
    protected function resolveExistingLicenseKey(): ?string
    {
        return AuthorityLicenseSync::resolveStoredLicenseKey();
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

    /**
     * GET /licenta — interfață principală (Bootstrap 5).
     */
    public function licenta()
    {
        return view('license-client::licenta.index', $this->licensePageData());
    }

    /**
     * POST /licenta/solicita — prima solicitare pe domeniu (cheie aleatoare) sau re-verificare cu cheia deja salvată.
     */
    public function solicita(Request $request)
    {
        if (LicenseState::resolve()['ok']) {
            return redirect()->route('license-client.licenta.index')
                ->with('license_error', 'Licența este deja activă; nu este nevoie de solicitare.');
        }

        $existing = $this->resolveExistingLicenseKey();
        $key = ($existing !== null && $existing !== '') ? $existing : 'req-' . bin2hex(random_bytes(16));
        $prefix = ($existing !== null && $existing !== '')
            ? 'Actualizare de la autoritate.'
            : 'Prima solicitare pe domeniu (cheie provizorie generată automat).';

        $hadExisting = ($existing !== null && $existing !== '');

        return $this->verifyKeyWithAuthorityAndSave($key, $prefix, $hadExisting);
    }

    /**
     * POST /licenta/activate — instalare licență din interfață.
     */
    public function upload(Request $request)
    {
        $request->validate(['license_key' => 'required|string']);
        $key = trim($request->input('license_key'));

        return $this->verifyKeyWithAuthorityAndSave($key, null, false);
    }

    /**
     * Verifică cheia la autoritate, verifică semnătura PEM și salvează storage/license.json.
     *
     * @param  string|null  $successPrefix  Prefix opțional pentru mesajul de succes (ex. solicitare din UI).
     * @param  bool  $skipActiveLocalOverwriteGuard  true = reîncarcă de la autoritate chiar dacă licența locală e OK (verify, cron).
     */
    protected function verifyKeyWithAuthorityAndSave(string $key, ?string $successPrefix, bool $skipActiveLocalOverwriteGuard): \Illuminate\Http\RedirectResponse
    {
        $result = AuthorityLicenseSync::sync($key, $skipActiveLocalOverwriteGuard, $successPrefix);

        if (! $result['ok']) {
            return redirect()->route('license-client.licenta.index')
                ->with('license_error', $result['error'] ?? 'Eroare la verificarea licenței.');
        }

        return redirect()->route('license-client.licenta.index')
            ->with('license_success', $result['success'] ?? 'Licența a fost verificată și salvată local.');
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
     * POST /licenta/verify — verifică licența locală cu autoritatea (aceeași logică ca solicitarea din interfață).
     */
    public function verify(Request $request)
    {
        $key = $this->resolveExistingLicenseKey();
        if ($key === null || $key === '') {
            return redirect()->route('license-client.licenta.index')
                ->with('license_error', 'Nu există nicio licență instalată sau fișierul este corupt.');
        }

        return $this->verifyKeyWithAuthorityAndSave($key, null, true);
    }
}
