<?php

declare(strict_types=1);

////	api_authenticate_request
// Authenticate a caller of the READ API (/api/torrents) and return the user to
// act as. Two paths:
//
//   1. Authorization header — `Authorization: Bearer <key>` (api_request_key).
//      Validated against $settings['api_keys']; the user may be a normal owner
//      (scoped to its own torrents) or the '*' admin (sees everything).
//   2. admin.php session — a logged-in admin resolves to the '*' admin. No CSRF
//      token is required on this read path: the session cookie is auto-sent,
//      but the response can't be read cross-origin, so a forged request leaks
//      nothing. (Mutations use api_authenticate_mutation, which DOES require a
//      CSRF token.)
//
// Refuses via tracker_error() (which exits) on any failure; the entry point
// pre-sets the JSON flag so those errors serialise as JSON unless ?xml is set.

/** @param PhoenixSettings $settings */
function api_authenticate_request(array $settings): string
{
    ////	Authorization header (no CSRF)
    require_once __DIR__.'/api.request.key.php';
    $key = api_request_key();
    if ($key !== '') {
        if (empty($settings['api_keys'])) {
            tracker_error('API is not enabled.');
        }
        require_once __DIR__.'/api.authenticate.key.php';
        $user = api_authenticate_key($settings, $key);
        if ($user === false) {
            tracker_error('API key is invalid.');
        }

        return $user;
    }

    ////	admin.php session (reads need no CSRF)
    require_once __DIR__.'/api.admin.session.php';
    if (api_admin_session_active()) {
        return '*';
    }

    tracker_error('Authorization required.');
}
