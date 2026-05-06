<?php

namespace Hearth\LicenseClient\Tests\Unit;

use Hearth\LicenseClient\Package;
use Hearth\LicenseClient\Tests\TestCase;

class PackageLicenseActivationPathTest extends TestCase
{
    public function test_license_activation_base_path_at_root(): void
    {
        config(['app.url' => 'https://example.test']);

        $this->assertSame('/licenta', Package::licenseActivationBasePath());
    }

    public function test_license_activation_base_path_with_subdirectory(): void
    {
        config(['app.url' => 'https://example.test/myapp/cms']);

        $this->assertSame('/myapp/cms/licenta', Package::licenseActivationBasePath());
    }
}
