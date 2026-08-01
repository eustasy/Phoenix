<?php

declare(strict_types=1);

////	auth_login_throttle_delay
// Compute the brute-force throttle delay (in seconds) for the Nth consecutive
// failed admin login. The delay scales linearly with the failure count and is
// capped at $max; a $base of 0 (or no failures) means no delay.
//
// This is per-session backoff, and the caller ADVERTISES it (429 +
// Retry-After) rather than sleeping through it: a blocking delay holds a PHP
// worker, costing the server more than the attacker. It applies only to a
// client that carries a session — one that discards its cookie is never
// tracked, and nothing here slows it down. Real per-IP rate limiting belongs at
// the proxy, as documented in APACHE.md / NGINX.md.
function auth_login_throttle_delay(int $fails, int $base, int $max): int
{
    if ($base <= 0 || $fails <= 0) {
        return 0;
    }

    return min($fails * $base, $max);
}
