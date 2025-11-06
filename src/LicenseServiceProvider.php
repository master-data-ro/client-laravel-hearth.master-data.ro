<?php

namespace Hearth\LicenseClient;

use Illuminate\Support\ServiceProvider;

class LicenseServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Nothing to bind for now
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\MakeLicenseServerCommand::class,
            ]);
        }
    }
}
