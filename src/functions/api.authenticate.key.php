<?php

declare(strict_types=1);

////	api_authenticate_key
// Looks up an API key in $settings['api_keys'] ('user' => sha256-hash pairs) and
// returns the user the key belongs to, or false when no user carries it. The
// stored values are SHA-256 hashes — the key itself is never persisted (see the
// admin API Keys page) — so we hash the presented key once and compare against
// each stored hash with hash_equals: timing-safe, and a fast hash is correct
// here because an API key is a high-entropy random token, not a low-entropy
// password that would warrant a slow one.

/** @param PhoenixSettings $settings */
function api_authenticate_key(array $settings, string $key): string|false
{
    if ($key === '') {
        return false;
    }

    $presented = hash('sha256', $key);
    foreach ($settings['api_keys'] as $user => $stored_hash) {
        if (hash_equals($stored_hash, $presented)) {
            return (string) $user;
        }
    }

    return false;
}
