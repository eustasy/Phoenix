<?php

declare(strict_types=1);

////	admin_edit_controller
// Renders the admin Edit Torrent page (page=edit): a form pre-filled with an
// existing torrent's fields, plus the submit handler (process=torrent_edit)
// that applies the change. The admin may edit any torrent. The info_hash
// arrives from the query string (initial load) or the form (submit), so it MUST
// pass maybe_binary_to_hex and be 40-char hex before any query; an invalid one
// bails via tracker_error. Verifies the CSRF token on submit. Dispatched by
// admin_panel_controller() for page=edit.

/** @param PhoenixSettings $settings */
function admin_edit_controller(mysqli $connection, array $settings): string
{
    require_once __DIR__.'/../functions/auth.csrf.token.php';
    require_once __DIR__.'/../functions/auth.csrf.verify.php';

    // CSRF only matters when a password (hence a session) is in play. (Mirrors
    // admin_add_controller / admin_torrents_controller.)
    $csrf_enabled = ! empty($settings['admin_password']);

    require_once __DIR__.'/../functions/sanitize.maybe_binary_to_hex.php';
    $raw = $_POST['info_hash'] ?? $_GET['info_hash'] ?? '';
    $info_hash = maybe_binary_to_hex(is_string($raw) ? $raw : '');
    if ($info_hash === false || strlen($info_hash) !== 40) {
        tracker_error('Info Hash is invalid.');
    }

    $process = '';
    if (! empty($_POST['process'])) {
        $process = htmlentities($_POST['process'], ENT_QUOTES, 'UTF-8');
    }

    $message = false;

    // Reject a state-changing POST whose CSRF token is missing or wrong; surface
    // a message and skip dispatch so the form still renders for a retry.
    if ($process !== '' && $csrf_enabled && ! auth_csrf_verify()) {
        $message = 'Security check failed. Please reload the page and try again.';
        $process = '';
    }

    if ($process === 'torrent_edit') {
        require_once __DIR__.'/admin.torrent.edit.php';
        $message = admin_torrent_edit_action($connection, $settings, $info_hash);
    }

    // Load the (possibly-updated) torrent so the form reflects the saved state.
    require_once __DIR__.'/../model/torrent.select.one.php';
    $torrent = torrent_select_one($connection, $settings, $info_hash);

    $csrf_token = $csrf_enabled ? auth_csrf_token() : '';

    require_once __DIR__.'/../views/html.admin.edit.php';

    return view_admin_edit_html($settings, $info_hash, $torrent, $message, $csrf_token);
}
