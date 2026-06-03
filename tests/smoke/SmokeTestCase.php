<?php

declare(strict_types=1);

namespace Phoenix\Tests\Smoke;

use PHPUnit\Framework\TestCase;

// Base class for the end-to-end smoke suite. Drives the real public/*.php entry
// points over HTTP against a running `php -S` server (SMOKE_BASE_URL). Uses the
// stream wrapper rather than a client dependency; redirects are NOT followed so
// installer/login 302s can be asserted directly.
abstract class SmokeTestCase extends TestCase
{
    protected function baseUrl(): string
    {
        $base = getenv('SMOKE_BASE_URL');

        return rtrim($base !== false && $base !== '' ? $base : 'http://127.0.0.1:8123', '/');
    }

    /**
     * Database credentials for the install step, from the environment.
     *
     * @return array<string, string>
     */
    protected function dbCreds(): array
    {
        return [
            'db_host' => getenv('SMOKE_DB_HOST') ?: '127.0.0.1',
            'db_user' => getenv('SMOKE_DB_USER') ?: 'phoenix',
            'db_pass' => getenv('SMOKE_DB_PASS') ?: 'phoenix_pass',
            'db_name' => getenv('SMOKE_DB_NAME') ?: 'phoenix',
            'db_prefix' => getenv('SMOKE_DB_PREFIX') ?: 'phoenix_',
        ];
    }

    /**
     * @param array<string, scalar> $query
     * @param array<string, scalar> $form
     * @return array{status: int, headers: list<string>, body: string}
     */
    protected function http(string $method, string $path, array $query = [], array $form = []): array
    {
        $url = $this->baseUrl().$path;
        if ($query !== []) {
            $url .= (str_contains($path, '?') ? '&' : '?').http_build_query($query);
        }

        $http = [
            'method' => $method,
            'ignore_errors' => true,  // capture the body on 4xx/5xx too
            'follow_location' => 0,   // assert redirects rather than chase them
            'timeout' => 10,
        ];
        if ($form !== []) {
            $http['header'] = "Content-Type: application/x-www-form-urlencoded\r\n";
            $http['content'] = http_build_query($form);
        }

        $body = @file_get_contents($url, false, stream_context_create(['http' => $http]));
        $headers = $http_response_header ?? [];

        $status = 0;
        if (isset($headers[0]) && preg_match('~HTTP/\S+\s+(\d+)~', $headers[0], $m)) {
            $status = (int) $m[1];
        }

        return [
            'status' => $status,
            'headers' => $headers,
            'body' => $body === false ? '' : $body,
        ];
    }

    /**
     * @param array<string, scalar> $query
     * @return array{status: int, headers: list<string>, body: string}
     */
    protected function get(string $path, array $query = []): array
    {
        return $this->http('GET', $path, $query);
    }

    /**
     * @param array<string, scalar> $form
     * @return array{status: int, headers: list<string>, body: string}
     */
    protected function post(string $path, array $form = []): array
    {
        return $this->http('POST', $path, [], $form);
    }

    /** @param array{status: int, headers: list<string>, body: string} $response */
    protected function headerValue(array $response, string $name): ?string
    {
        foreach ($response['headers'] as $line) {
            if (stripos($line, $name.':') === 0) {
                return trim(substr($line, strlen($name) + 1));
            }
        }

        return null;
    }
}
