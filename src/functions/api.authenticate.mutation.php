<?php

declare(strict_types=1);

////	api_authenticate_mutation
// Authenticate a caller of the mutating API endpoints (list/delist/delete) and
// return the user identity to act as. Two paths, key-first:
//
//   1. API key — when a `key` parameter is present (POST or GET), authenticate
//      it via api_authenticate_request(). No CSRF: an API key is not an ambient
//      credential a browser attaches on its own, so it can't be forged by a
//      cross-site request. The returned user may be a normal owner or the '*'
//      admin.
//   2. admin.php session — when no key is supplied, a logged-in admin session
//      authorises as the '*' admin, but ONLY with a valid CSRF token, because
//      the session cookie IS sent automatically by the browser. Resolves to
//      '*'.
//
// Refuses via tracker_error() (which exits) on any failure; the entry point
// pre-sets the JSON flag so those errors serialise as JSON unless ?xml is set.

/** @param PhoenixSettings $settings */
function api_authenticate_mutation(array $settings): string
{
    ////	API key path (no CSRF)
    if (isset($_POST['key']) || isset($_GET['key'])) {
        require_once __DIR__.'/api.authenticate.request.php';

        return api_authenticate_request($settings);
    }

    ////	Admin session path (CSRF required)
    require_once __DIR__.'/api.admin.session.php';
    if (api_admin_session_active()) {
        require_once __DIR__.'/auth.csrf.verify.php';
        if (! auth_csrf_verify()) {
            tracker_error('CSRF token is invalid.');
        }

        return '*';
    }

    ////	Neither credential presented
    tracker_error('API key is invalid.');
}
