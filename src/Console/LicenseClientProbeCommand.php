<?php

namespace Hearth\LicenseClient\Console;

use Hearth\LicenseClient\LicenseState;
use Hearth\LicenseClient\Package;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class LicenseClientProbeCommand extends Command
{
    protected $signature = 'license-client:probe {--json : Ieșire JSON pentru CI/deploy}';

    protected $description = 'Verificare one-shot: JWKS/PEM, versiune pachet, license.json, LicenseState, cale /licenta.';

    public function handle(): int
    {
        $authority = rtrim(Package::authorityUrl(), '/');
        $jwksUrl = $authority . '/.well-known/jwks.json';
        $pemUrl = $authority . '/' . ltrim(Package::pemEndpoint(), '/');
        $verifyUrl = $authority . '/' . ltrim(Package::verifyEndpoint(), '/');

        $jwksOk = false;
        $jwksHttp = null;
        $jwksError = null;
        try {
            $jwksResp = Http::timeout(Package::remoteTimeout())->get($jwksUrl);
            $jwksHttp = $jwksResp->status();
            $jwksOk = $jwksResp->successful();
            if (! $jwksOk) {
                $jwksError = 'HTTP ' . $jwksResp->status();
            }
        } catch (\Throwable $e) {
            $jwksError = $e->getMessage();
        }

        $pemOk = false;
        $pemHttp = null;
        $pemError = null;
        try {
            $pemResp = Http::timeout(Package::remoteTimeout())->get($pemUrl);
            $pemHttp = $pemResp->status();
            $pemOk = $pemResp->successful() && str_contains($pemResp->body(), 'BEGIN PUBLIC KEY');
            if (! $pemOk && $pemError === null) {
                $pemError = $pemResp->successful() ? 'body fără PEM' : 'HTTP ' . $pemResp->status();
            }
        } catch (\Throwable $e) {
            $pemError = $e->getMessage();
        }

        $verifyReachable = false;
        $verifyHttp = null;
        $verifyError = null;
        try {
            $vResp = Http::timeout(Package::remoteTimeout())->post($verifyUrl, [
                'license_key' => '__hearth_probe__',
                'domain' => 'probe.invalid',
            ]);
            $verifyHttp = $vResp->status();
            $verifyReachable = true;
            if ($vResp->status() >= 500) {
                $verifyError = 'HTTP ' . $vResp->status();
            }
        } catch (\Throwable $e) {
            $verifyError = $e->getMessage();
        }

        $licensePath = storage_path('license.json');
        $licenseFileExists = is_file($licensePath);
        $state = LicenseState::resolve();
        $activationPath = Package::licenseActivationBasePath();

        $composerPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'composer.json';
        $packageVersion = 'unknown';
        if (is_file($composerPath)) {
            $c = json_decode((string) file_get_contents($composerPath), true);
            if (is_array($c) && ! empty($c['version'])) {
                $packageVersion = (string) $c['version'];
            }
        }

        $payload = [
            'package_version' => $packageVersion,
            'jwks' => [
                'url' => $jwksUrl,
                'ok' => $jwksOk,
                'http_status' => $jwksHttp,
                'error' => $jwksError,
            ],
            'pem' => [
                'url' => $pemUrl,
                'ok' => $pemOk,
                'http_status' => $pemHttp,
                'error' => $pemError,
            ],
            'verify_post' => [
                'url' => $verifyUrl,
                'reachable' => $verifyReachable,
                'http_status' => $verifyHttp,
                'error' => $verifyError,
            ],
            'license_file' => [
                'path' => $licensePath,
                'exists' => $licenseFileExists,
            ],
            'license_state' => [
                'ok' => $state['ok'],
                'code' => $state['code'],
            ],
            'license_activation_path' => $activationPath,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->line('<fg=cyan>hearth/license-client — probe</>');
        $this->line('Versiune pachet (composer): <info>' . $packageVersion . '</>');
        $this->line('license.json: ' . ($licenseFileExists ? '<info>există</>' : '<fg=yellow>lipsește</>') . ' <comment>' . $licensePath . '</>');
        $this->line('LicenseState: ' . ($state['ok'] ? '<info>ok</>' : '<fg=yellow>' . $state['code'] . '</>'));
        $this->line('Cale UI licență: <info>' . $activationPath . '</>');
        $this->newLine();
        $this->line('JWKS: ' . ($jwksOk ? '<info>OK</>' : '<fg=red>FAIL</>') . ($jwksHttp !== null ? ' (HTTP ' . $jwksHttp . ')' : '') . ($jwksError ? ' — ' . $jwksError : ''));
        $this->line('PEM:  ' . ($pemOk ? '<info>OK</>' : '<fg=red>FAIL</>') . ($pemHttp !== null ? ' (HTTP ' . $pemHttp . ')' : '') . ($pemError ? ' — ' . $pemError : ''));
        $this->line('POST verify: ' . ($verifyReachable ? '<info>răspuns primit</>' : '<fg=red>fără răspuns</>') . ($verifyHttp !== null ? ' (HTTP ' . $verifyHttp . ')' : '') . ($verifyError ? ' — ' . $verifyError : ''));

        return self::SUCCESS;
    }
}
