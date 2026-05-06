<?php

namespace Hearth\LicenseClient\Console;

use Hearth\LicenseClient\AuthorityLicenseSync;
use Hearth\LicenseClient\LicenseState;
use Illuminate\Console\Command;

class LicenseClientSyncCommand extends Command
{
    protected $signature = 'license-client:sync {--quiet-sync : Doar cod de ieșire, fără mesaje pe stdout}';

    protected $description = 'Re-verifică licența salvată la autoritate (folosit și de scheduler la 5 minute).';

    public function handle(): int
    {
        if (AuthorityLicenseSync::shouldSkipRemoteSync()) {
            if (! $this->option('quiet-sync')) {
                $this->comment('license-client:sync — sărit (mod autoritate sau același host ca autoritatea).');
            }

            return self::SUCCESS;
        }

        $key = AuthorityLicenseSync::resolveStoredLicenseKey();
        if ($key === null || $key === '') {
            if (! $this->option('quiet-sync')) {
                $this->comment('license-client:sync — nu există cheie salvată; nimic de sincronizat.');
            }

            return self::SUCCESS;
        }

        $result = AuthorityLicenseSync::sync($key, true, null);

        if (! $result['ok']) {
            logger()->warning('license-client:sync failed: ' . ($result['error'] ?? 'unknown'));
            if (! $this->option('quiet-sync')) {
                $this->error($result['error'] ?? 'Eroare necunoscută.');
            }

            return self::FAILURE;
        }

        $after = LicenseState::resolve();
        logger()->info('license-client:sync ok', [
            'license_ok' => $after['ok'],
            'license_code' => $after['code'],
        ]);

        if (! $this->option('quiet-sync')) {
            $this->info($result['success'] ?? 'Sincronizare reușită.');
            $this->line('Stare după sync: ' . ($after['ok'] ? 'validă' : 'nevalidă') . ' (' . $after['code'] . ').');
        }

        return self::SUCCESS;
    }
}
