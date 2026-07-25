<?php

namespace Hearth\LicenseClient;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class AuthorityHttp
{
    /**
     * @param  array<string, mixed>  $body
     */
    public static function post(string $path, array $body = []): Response
    {
        return self::send('post', $path, $body);
    }

    public static function get(string $path): Response
    {
        return self::send('get', $path);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private static function send(string $method, string $path, array $body = []): Response
    {
        $url = rtrim(Package::authorityUrl(), '/').'/'.trim($path, '/');
        $pending = Http::timeout(Package::remoteTimeout());

        return $method === 'get'
            ? $pending->get($url)
            : $pending->post($url, $body);
    }
}
