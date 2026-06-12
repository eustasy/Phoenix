<?php

declare(strict_types=1);

////	api_authenticate_request
// Shared front door for every management-API controller: refuse when no keys
// are configured (`API is not enabled.`), read `key` from POST or GET, validate
// it timing-safely via api_authenticate_key(), and exit via tracker_error()
// with `API key is invalid.` on failure. Returns the user the key belongs to so
// the controller can attribute and scope its work. Keeping auth here (rather
// than in the entry point) leaves it in unit-testable controller space.
//
// tracker_error() lives in the bootstrap, not here; entry points pre-set the
// JSON flag so these errors serialise as JSON unless the caller asked for XML.

/** @param PhoenixSettings $settings */
function api_authenticate_request(array $settings): string
{
    // No configured keys means the API is off — refuse before reading input.
    if (empty($settings['api_keys'])) {
        tracker_error('API is not enabled.');
    }

    require_once __DIR__.'/api.authenticate.key.php';
    $key = $_POST['key'] ?? $_GET['key'] ?? '';
    $user = api_authenticate_key($settings, is_string($key) ? $key : '');
    if ($user === false) {
        tracker_error('API key is invalid.');
    }

    return $user;
}
