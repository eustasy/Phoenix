<?php

declare(strict_types=1);

////	api_request_key
// Extract the API key from the request's `Authorization` header. The documented
// scheme is `Authorization: Bearer <key>`; the `Bearer ` prefix is stripped
// (case-insensitive), and a bare `Authorization: <key>` is tolerated too.
// Returns '' when no Authorization header is present.
//
// The key is taken from the header — never a query/body parameter — so it stays
// out of access logs and browser history. PHP-FPM and the built-in server
// expose it as $_SERVER['HTTP_AUTHORIZATION']; some Apache setups surface only
// the REDIRECT_ copy, and getallheaders() is the last-resort fallback. (Apache
// + mod_php may strip Authorization unless CGIPassAuth is on — see APACHE.md.)

function api_request_key(): string
{
    $header = '';
    if (isset($_SERVER['HTTP_AUTHORIZATION']) && is_string($_SERVER['HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) && is_string($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp((string) $name, 'Authorization') === 0 && is_string($value)) {
                $header = $value;
                break;
            }
        }
    }

    $header = trim($header);
    if ($header === '') {
        return '';
    }

    // Strip the "Bearer " scheme prefix when present.
    if (stripos($header, 'Bearer ') === 0) {
        return trim(substr($header, 7));
    }

    return $header;
}
