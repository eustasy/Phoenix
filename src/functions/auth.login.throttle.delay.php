<?php

declare(strict_types=1);

////	auth_login_throttle_delay
// Compute the brute-force throttle delay (in seconds) for the Nth consecutive
// failed admin login. The delay scales linearly with the failure count and is
// capped at $max; a $base of 0 (or no failures) means no delay.
//
// This is per-session backoff — it slows a client that keeps its session
// cookie, and a cookie-less attacker still pays the base delay on each request.
// It complements, and does not replace, the per-IP proxy rate-limiting
// documented in APACHE.md / NGINX.md.
function auth_login_throttle_delay(int $fails, int $base, int $max): int
{
    if ($base <= 0 || $fails <= 0) {
        return 0;
    }

    return min($fails * $base, $max);
}
