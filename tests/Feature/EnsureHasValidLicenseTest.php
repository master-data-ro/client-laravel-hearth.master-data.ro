<?php

namespace Hearth\LicenseClient\Tests\Feature;

use Hearth\LicenseClient\Package;
use Hearth\LicenseClient\Tests\TestCase;

class EnsureHasValidLicenseTest extends TestCase
{
    public function test_expects_json_receives_403_with_stable_body_and_headers(): void
    {
        $response = $this->getJson('/probe-secret');

        $response->assertStatus(403);
        $response->assertJsonStructure(['message', 'license_code']);
        $response->assertJson([
            'license_code' => 'missing',
        ]);
        $response->assertHeader(strtolower(Package::HEADER_LICENSE_CODE), 'missing');
        $response->assertHeader(strtolower(Package::HEADER_LICENSE_OK), '0');
    }

    public function test_html_request_redirects_to_licenta(): void
    {
        $response = $this->get('/probe-secret');

        $response->assertRedirect(route('license-client.licenta.index'));
    }
}
