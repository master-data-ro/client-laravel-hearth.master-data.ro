<?php

namespace Hearth\LicenseClient;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class AuthorityHttp
{
    /**
     * @param  array<string, mixed>  $body
     */
    public static function post(string $path, array $body = []): Response
    {
        return self::requestWithFailover('post', $path, $body);
    }

    public static function get(string $path): Response
    {
        return self::requestWithFailover('get', $path);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private static function requestWithFailover(string $method, string $path, array $body = []): Response
    {
        $primary = rtrim(Package::authorityUrl(), '/');
        $fallback = rtrim(Package::authorityUrlFallback(), '/');
        $normalizedPath = '/'.trim($path, '/');

        try {
            $response = self::send($method, $primary, $normalizedPath, $body);
            if (self::isSuccessfulResponse($response)) {
                return $response;
            }

            $failure = self::describeFailure($response);
        } catch (\Throwable $e) {
            $failure = [
                'reason' => str_contains(strtolower($e->getMessage()), 'timed out') ? 'connection_timeout' : 'connection_error',
                'status' => null,
            ];
            $response = null;
        }

        if (! Package::legacyFallbackEnabled() || $fallback === '' || strcasecmp($fallback, $primary) === 0) {
            if ($response instanceof Response) {
                return $response;
            }

            throw new \RuntimeException($failure['reason']);
        }

        self::logFallback($primary, $fallback, $failure['reason'], $failure['status']);

        return self::send($method, $fallback, $normalizedPath, $body);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private static function send(string $method, string $baseUrl, string $path, array $body = []): Response
    {
        $url = rtrim($baseUrl, '/').$path;
        $pending = Http::timeout(Package::remoteTimeout());

        return $method === 'get'
            ? $pending->get($url)
            : $pending->post($url, $body);
    }

    private static function isSuccessfulResponse(Response $response): bool
    {
        return $response->successful();
    }

    /**
     * @return array{reason: string, status: ?int}
     */
    private static function describeFailure(Response $response): array
    {
        return [
            'reason' => 'http_'.$response->status(),
            'status' => $response->status(),
        ];
    }

    private static function logFallback(string $primary, string $fallback, string $reason, ?int $status): void
    {
        Log::warning('scmc_integration_primary_failed_using_fallback', [
            'integration' => 'hearth',
            'primary_url' => $primary,
            'fallback_url' => $fallback,
            'reason' => $reason,
            'primary_status' => $status,
        ]);
    }
}
