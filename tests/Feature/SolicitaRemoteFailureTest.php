<?php

namespace Hearth\LicenseClient\Tests\Feature;

use Hearth\LicenseClient\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class SolicitaRemoteFailureTest extends TestCase
{
    public function test_solicita_does_not_create_license_file_when_authority_rejects(): void
    {
        $this->assertFileDoesNotExist(storage_path('license.json'));

        Http::fake([
            'https://hearth.master-data.ro/*' => Http::response(['error' => 'invalid'], 422),
        ]);

        $response = $this->post(route('license-client.licenta.solicita'));

        $response->assertRedirect(route('license-client.licenta.index'));
        $response->assertSessionHas('license_error');
        $this->assertFileDoesNotExist(storage_path('license.json'));
    }

    public function test_solicita_sends_provisional_req_key_when_license_file_missing(): void
    {
        $this->assertFileDoesNotExist(storage_path('license.json'));

        $postedLicenseKey = null;
        Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$postedLicenseKey) {
            if (str_contains($request->url(), 'hearth.master-data.ro') && str_contains($request->url(), '/api/verify')) {
                $postedLicenseKey = $request->data()['license_key'] ?? null;

                return Http::response(['error' => 'stub_invalid_payload'], 422);
            }

            return Http::response(['error' => 'unexpected'], 500);
        });

        $this->post(route('license-client.licenta.solicita'))
            ->assertRedirect(route('license-client.licenta.index'));

        $this->assertNotNull($postedLicenseKey);
        $this->assertMatchesRegularExpression('/^req-[a-f0-9]{32}$/', $postedLicenseKey);
        $this->assertFileDoesNotExist(storage_path('license.json'));
    }
}
