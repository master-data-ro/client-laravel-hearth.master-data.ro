<?php

namespace Hearth\LicenseClient\Console;

use Hearth\LicenseClient\Package;
use Illuminate\Console\Command;

class LicenseClientStatusCommand extends Command
{
    protected $signature = 'license-client:status';

    protected $description = 'Diagnose why license enforcement may not run (manifest, kernel middleware, authority bypass).';

    public function handle(): int
    {
        $this->line('<fg=cyan>hearth/license-client</> — diagnostic');

        $manifestPath = base_path('bootstrap/cache/packages.php');
        if (! is_file($manifestPath)) {
            $this->warn('Lipsește <comment>bootstrap/cache/packages.php</>. Rulează: <info>php artisan package:discover</info>');
        } else {
            $raw = @file_get_contents($manifestPath) ?: '';
            if (str_contains($raw, 'hearth/license-client') || str_contains($raw, 'hearth\\\\license-client')) {
                $this->info('Manifest pachete: hearth/license-client apare în cache.');
            } else {
                $this->error('Manifest pachete: NU apare hearth/license-client. Șterge cache-ul și redescoperă:');
                $this->line('  <info>rm -f bootstrap/cache/packages.php</info>');
                $this->line('  <info>php artisan package:discover --ansi</info>');
                $this->line('Sau adaugă manual în <comment>bootstrap/providers.php</comment>:');
                $this->line('  <info>Hearth\\LicenseClient\\LicenseServiceProvider::class,</info>');
            }
        }

        $loaded = $this->laravel->getLoadedProviders();
        $provider = 'Hearth\\LicenseClient\\LicenseServiceProvider';
        if (! empty($loaded[$provider])) {
            $this->info('Provider încărcat: DA.');
        } else {
            $this->error('Provider încărcat: NU. Laravel nu a bootat pachetul (discover / providers).');
        }

        $priv = Package::authoritySigningPrivateKeyPath();
        if (is_file($priv)) {
            $this->warn('Există <comment>storage/keys/private.pem</comment> — aplicația poate fi tratată ca <fg=yellow>AUTORITATE</> și middleware-ul de licență poate fi oprit.');
        }

        $authHost = parse_url(rtrim(Package::authorityUrl(), '/'), PHP_URL_HOST);
        $appHost = parse_url((string) (config('app.url') ?: env('APP_URL', '')), PHP_URL_HOST);
        if ($authHost && $appHost && strcasecmp((string) $authHost, (string) $appHost) === 0) {
            $this->warn('APP_URL are același host ca autoritatea — enforcement oprit (mod autoritate).');
        }

        $licenseFile = storage_path('license.json');
        $this->line('Fișier licență: ' . (is_file($licenseFile) ? '<info>există</>' : '<fg=red>lipsește</>') . ' <comment>' . $licenseFile . '</>');

        $this->line('Cale UI activare licență: <info>' . Package::licenseActivationBasePath() . '</> (din <comment>APP_URL</> / <comment>app.url</>).');

        $this->line('Sincronizare automată: pachetul înregistrează <info>license-client:sync</info> la <info>everyFiveMinutes()</info> (reîncarcă licența de la autoritate). Necesită ca <info>schedule:run</info> să fie în cron — vezi README.');

        if ($this->laravel->routesAreCached()) {
            $this->warn('Cache rute: <fg=yellow>ACTIV</>. Dacă ai instalat/actualizat pachetul după <info>route:cache</info>, rutele UI pot lipsi — rulează <info>php artisan route:clear</info> sau regenerează cache-ul.');
        }

        $this->newLine();
        $this->comment('Middleware-ul global se atașează la primul request HTTP (din Artisan stiva poate fi goală — e normal).');

        $this->newLine();
        $this->line('După remediere: <info>php artisan optimize:clear</info> și reîncarcă site-ul în browser.');

        $this->newLine();
        $this->comment('Verificare one-shot (reachability JWKS/PEM/verify, versiune pachet, fișier licență, LicenseState, cale /licenta):');
        $this->line('  <info>php artisan license-client:probe</info>  sau pentru CI: <info>php artisan license-client:probe --json</info>');

        return 0;
    }
}
