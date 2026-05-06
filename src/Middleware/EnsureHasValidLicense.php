<?php

namespace Hearth\LicenseClient\Middleware;

use Closure;
use Hearth\LicenseClient\LicenseState;
use Hearth\LicenseClient\Messages;
use Hearth\LicenseClient\Package;
use Illuminate\Http\JsonResponse;

class EnsureHasValidLicense
{
    public function handle($request, Closure $next)
    {
        // Allow in console (artisan) so CLI tasks continue to work. For web
        // requests, do not allow exceptions: block until a valid license exists.
        if (app()->runningInConsole()) {
            return $next($request);
        }

        $path = $request->getPathInfo();

        // Laravel /up — exact match only (prefix /up would match /upload).
        if ($path === '/up') {
            return $this->withLicenseProbeMetadata($request, $next);
        }

        // /licenta*: doar când licența NU e validă (altfel redirect acasă). URL-ul autorității nu apare în UI.
        if (Package::isLicenseActivationPath($path)) {
            if (LicenseState::resolve()['ok']) {
                return redirect('/');
            }

            return $next($request);
        }

        // Allow whitelisted paths (prefix match), except /health which gets license metadata on the response.
        $whitelist = Package::whitelist();
        foreach ($whitelist as $allowed) {
            if ($allowed !== '' && str_starts_with($path, $allowed)) {
                if ($allowed === '/health') {
                    return $this->withLicenseProbeMetadata($request, $next);
                }

                return $next($request);
            }
        }

        $state = LicenseState::resolve();
        if (! $state['ok']) {
            $messageKey = match ($state['code']) {
                LicenseState::CODE_MISSING => 'not_present',
                LicenseState::CODE_INVALID => 'invalid',
                LicenseState::CODE_NOT_ACTIVE => 'not_active',
                LicenseState::CODE_EXPIRED => 'expired',
                LicenseState::CODE_DOMAIN_MISMATCH => 'domain_mismatch',
                default => 'invalid',
            };
            $message = Messages::get($messageKey);

            return response()->view('license-client::blocked', [
                'message' => $message,
                'license_code' => $state['code'],
            ], 403);
        }

        return $next($request);
    }

    /**
     * Let the probe through (no 403) but expose license status in headers and JSON body when applicable.
     */
    protected function withLicenseProbeMetadata($request, Closure $next)
    {
        $license = LicenseState::healthPayload();
        $response = $next($request);

        $response->headers->set('X-License-Ok', $license['valid'] ? '1' : '0');
        $response->headers->set('X-License-Code', $license['code']);

        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);
            if (! is_array($data)) {
                $data = ['status' => $data];
            }
            $data['license'] = $license;
            $response->setData($data);
        }

        return $response;
    }
}
