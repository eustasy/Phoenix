<?php

declare(strict_types=1);

////	api_authenticate_key
// Looks up an API key in $settings['api_keys'] ('user' => 'key' pairs) and
// returns the user the key belongs to, or false when no user carries it.
// Comparison is timing-safe (hash_equals) so a key can't be recovered
// byte-by-byte from response timing.

/** @param PhoenixSettings $settings */
function api_authenticate_key(array $settings, string $key): string|false
{
    if ($key === '') {
        return false;
    }

    foreach ($settings['api_keys'] as $user => $user_key) {
        if (hash_equals($user_key, $key)) {
            return (string) $user;
        }
    }

    return false;
}
