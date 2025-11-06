<?php

namespace Hearth\LicenseClient\Middleware;

use Closure;
use Hearth\LicenseClient\Encryption;

class EnsureHasValidLicense
{
    public function handle($request, Closure $next)
    {
        // Allow in console (artisan) so CLI tasks continue to work. For web
        // requests, do not allow exceptions: block until a valid license exists.
        if (app()->runningInConsole()) {
            return $next($request);
        }

        $store = storage_path('license.json');
        if (!file_exists($store)) {
            $message = \Hearth\LicenseClient\Messages::get('not_present');
            return response()->view('license-client::blocked', ['message' => $message], 403);
        }

        $raw = file_get_contents($store);
        if (empty($raw)) {
            return response('License required', 403);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || empty($decoded['payload']) || empty($decoded['encrypted'])) {
            $message = \Hearth\LicenseClient\Messages::get('invalid');
            return response()->view('license-client::blocked', ['message' => $message], 403);
        }

        try {
            $plaintext = Encryption::decryptString($decoded['payload']);
            $obj = json_decode($plaintext, true);
        } catch (Throwable $e) {
            $message = \Hearth\LicenseClient\Messages::get('invalid');
            return response()->view('license-client::blocked', ['message' => $message], 403);
        }

        if (empty($obj) || empty($obj['license_key'])) {
            $message = \Hearth\LicenseClient\Messages::get('invalid');
            return response()->view('license-client::blocked', ['message' => $message], 403);
        }

        // Require authority-declared validity flag
        $validFlag = $obj['data']['valid'] ?? null;
        if ($validFlag !== true) {
            $message = \Hearth\LicenseClient\Messages::get('not_active');
            return response()->view('license-client::blocked', ['message' => $message], 403);
        }

        // optional: expire check if present
        $expires = $obj['data']['expires_at'] ?? $obj['data']['expires'] ?? null;
        if ($expires) {
            try {
                $expTs = strtotime($expires);
                if ($expTs !== false && $expTs < time()) {
                    $message = config('license-client.messages.expired');
                    return response()->view('license-client::blocked', ['message' => $message], 403);
                }
            } catch (\Throwable $e) {
                // ignore parse errors and allow if not parseable
            }
        }

        // optional: domain match
        $appUrl = config('app.url') ?? env('APP_URL', '');
        $host = parse_url($appUrl, PHP_URL_HOST) ?: gethostname();
        if (!empty($obj['domain']) && $obj['domain'] !== $host) {
            $message = \Hearth\LicenseClient\Messages::get('domain_mismatch');
            return response()->view('license-client::blocked', ['message' => $message], 403);
        }

        // license looks okay, allow request
        return $next($request);
    }
}
