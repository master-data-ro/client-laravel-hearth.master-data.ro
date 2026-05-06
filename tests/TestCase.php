<?php

namespace Hearth\LicenseClient\Tests;

use Hearth\LicenseClient\LicenseServiceProvider;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [LicenseServiceProvider::class];
    }

    protected function defineRoutes($router): void
    {
        $router->get('/probe-secret', fn () => 'ok');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    protected function tearDown(): void
    {
        $license = storage_path('license.json');
        if (is_file($license)) {
            unlink($license);
        }
        parent::tearDown();
    }
}
