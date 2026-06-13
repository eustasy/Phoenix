<?php

declare(strict_types=1);

////	api_authenticate_mutation
// Authenticate a caller of the WRITE API (/api/torrent/*: add, list, delist,
// delete) and return the user to act as. Two paths:
//
//   1. Authorization header — `Authorization: Bearer <key>` (api_request_key).
//      No CSRF: a key isn't an ambient credential a browser attaches on its
//      own, so it can't be forged cross-site. The user may be a normal owner
//      (scoped to its own torrents) or the '*' admin (any torrent).
//   2. admin.php session — a logged-in admin resolves to the '*' admin, but
//      ONLY with a valid CSRF token: the session cookie IS sent automatically
//      by the browser, so a state change needs the token to prove intent.
//
// Refuses via tracker_error() (which exits) on any failure; the entry point
// pre-sets the JSON flag so those errors serialise as JSON unless ?xml is set.

/** @param PhoenixSettings $settings */
function api_authenticate_mutation(array $settings): string
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

    ////	admin.php session (state change requires a CSRF token)
    require_once __DIR__.'/api.admin.session.php';
    if (api_admin_session_active()) {
        require_once __DIR__.'/auth.csrf.verify.php';
        if (! auth_csrf_verify()) {
            tracker_error('CSRF token is invalid.');
        }

        return '*';
    }

    tracker_error('Authorization required.');
}
