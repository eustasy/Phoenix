<?php

declare(strict_types=1);

namespace Phoenix\Tests\Smoke;

use PHPUnit\Framework\TestCase;

// Base class for the end-to-end smoke suite. Drives the real public/*.php entry
// points over HTTP against a running `php -S` server (SMOKE_BASE_URL). Uses the
// stream wrapper rather than a client dependency; redirects are NOT followed so
// installer/login 302s can be asserted directly. Supports sending request
// headers (e.g. a Cookie) so the authenticated admin panel can be reached.
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
     * @param array<string, string> $headers
     * @param string|null $rawBody pre-built request body; when set it replaces
     *                             the urlencoded $form body (the caller owns the
     *                             Content-Type header, e.g. multipart uploads)
     * @return array{status: int, headers: list<string>, body: string}
     */
    protected function http(string $method, string $path, array $query = [], array $form = [], array $headers = [], ?string $rawBody = null): array
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
        if ($rawBody !== null) {
            $http['content'] = $rawBody;
        } elseif ($form !== []) {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            $http['content'] = http_build_query($form);
        }
        if ($headers !== []) {
            $header_lines = '';
            foreach ($headers as $name => $value) {
                $header_lines .= $name.': '.$value."\r\n";
            }
            $http['header'] = $header_lines;
        }

        $body = @file_get_contents($url, false, stream_context_create(['http' => $http]));
        $responseHeaders = $http_response_header ?? [];

        $status = 0;
        if (isset($responseHeaders[0]) && preg_match('~HTTP/\S+\s+(\d+)~', $responseHeaders[0], $m)) {
            $status = (int) $m[1];
        }

        return [
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => $body === false ? '' : $body,
        ];
    }

    /**
     * @param array<string, scalar> $query
     * @param array<string, string> $headers
     * @return array{status: int, headers: list<string>, body: string}
     */
    protected function get(string $path, array $query = [], array $headers = []): array
    {
        return $this->http('GET', $path, $query, [], $headers);
    }

    /**
     * @param array<string, scalar> $form
     * @param array<string, string> $headers
     * @return array{status: int, headers: list<string>, body: string}
     */
    protected function post(string $path, array $form = [], array $headers = []): array
    {
        return $this->http('POST', $path, [], $form, $headers);
    }

    /**
     * POST a multipart/form-data body — http()/post() only emit urlencoded
     * bodies, so this hand-builds the multipart envelope (text fields + a single
     * named file part) and sends it through the same stream context. Used to
     * drive the API's `.torrent` upload path end-to-end.
     *
     * @param array<string, scalar> $fields text form fields
     * @param array{name: string, filename: string, content: string, type?: string} $file
     * @return array{status: int, headers: list<string>, body: string}
     */
    protected function postMultipart(string $path, array $fields, array $file): array
    {
        $boundary = '----PhoenixSmoke'.bin2hex(random_bytes(8));
        $eol = "\r\n";

        $body = '';
        foreach ($fields as $name => $value) {
            $body .= '--'.$boundary.$eol;
            $body .= 'Content-Disposition: form-data; name="'.$name.'"'.$eol.$eol;
            $body .= $value.$eol;
        }
        $body .= '--'.$boundary.$eol;
        $body .= 'Content-Disposition: form-data; name="'.$file['name'].'"; filename="'.$file['filename'].'"'.$eol;
        $body .= 'Content-Type: '.($file['type'] ?? 'application/octet-stream').$eol.$eol;
        $body .= $file['content'].$eol;
        $body .= '--'.$boundary.'--'.$eol;

        return $this->http('POST', $path, [], [], [
            'Content-Type' => 'multipart/form-data; boundary='.$boundary,
            'Content-Length' => (string) strlen($body),
        ], $body);
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

    /**
     * Extract the per-session CSRF token from a rendered admin form (the hidden
     * `csrf` field). State-changing admin POSTs require it (see #59).
     */
    protected function csrfToken(string $body): ?string
    {
        if (preg_match('~name="csrf" value="([^"]*)"~', $body, $m) && $m[1] !== '') {
            return $m[1];
        }

        return null;
    }

    /**
     * Extract the session cookie (as a ready-to-send `PHPSESSID=…` pair) from a
     * response's Set-Cookie headers. Returns the LAST match — login regenerates
     * the session id, so the final Set-Cookie carries the authenticated one.
     *
     * @param array{status: int, headers: list<string>, body: string} $response
     */
    protected function sessionCookie(array $response): ?string
    {
        $cookie = null;
        foreach ($response['headers'] as $line) {
            if (stripos($line, 'Set-Cookie:') === 0 && preg_match('~(PHPSESSID=[^;]+)~', $line, $m)) {
                $cookie = $m[1];
            }
        }

        return $cookie;
    }

    /**
     * Run a bin/ cron script in a fresh PHP process — these aren't HTTP
     * endpoints. PCOV is enabled with the same prepend the server uses, so the
     * script's coverage lands in SMOKE_COV_DIR too (inherited from the env).
     *
     * @return array{exit: int, stdout: string, stderr: string}
     */
    protected function runCli(string $script): array
    {
        $root = dirname(__DIR__, 2);
        $proc = proc_open(
            [
                PHP_BINARY,
                '-d', 'pcov.enabled=1',
                '-d', 'pcov.directory='.$root,
                '-d', 'pcov.exclude=~/(vendor|tests)/~',
                '-d', 'auto_prepend_file='.$root.'/tests/smoke/coverage-prepend.php',
                $root.'/bin/'.$script,
            ],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        $this->assertIsResource($proc);
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['exit' => proc_close($proc), 'stdout' => $stdout, 'stderr' => $stderr];
    }

    /**
     * Direct mysqli connection to the smoke database — for seeding and verifying
     * effects of the CLI cron scripts that aren't observable over HTTP.
     */
    protected function db(): \mysqli
    {
        $c = $this->dbCreds();
        mysqli_report(MYSQLI_REPORT_OFF);
        $db = mysqli_connect($c['db_host'], $c['db_user'], $c['db_pass'], $c['db_name']);
        $this->assertInstanceOf(\mysqli::class, $db);

        return $db;
    }

    /** Run a COUNT/scalar query and return the first column of the first row as int. */
    protected function scalar(\mysqli $db, string $sql): int
    {
        $result = mysqli_query($db, $sql);
        $this->assertInstanceOf(\mysqli_result::class, $result);
        $row = mysqli_fetch_row($result);

        return (int) ($row[0] ?? 0);
    }

    /**
     * Append a `$settings[...] = ...;` override to the installed config. Loaded
     * last by settings_load(), so it wins over the value the installer wrote.
     */
    protected function appendConfigOverride(string $assignment): void
    {
        file_put_contents(
            dirname(__DIR__, 2).'/config/phoenix.custom.php',
            PHP_EOL.$assignment.PHP_EOL,
            FILE_APPEND,
        );
    }

    /** Turn on the cron maintenance path (the installer leaves it off). */
    protected function enableCleanWithCron(): void
    {
        $this->appendConfigOverride("\$settings['clean_with_cron'] = true;");
    }

    /** Close the tracker (the installer opens it). */
    protected function closeTracker(): void
    {
        $this->appendConfigOverride("\$settings['open_tracker'] = false;");
    }
}
