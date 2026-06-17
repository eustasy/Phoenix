<?php

declare(strict_types=1);

////	view_admin_add_html
// Render the admin Add Torrent page: a form for the torrent fields, with a
// drop zone that parses a dropped/picked .torrent IN THE BROWSER (the same way
// the magnet generator does) and fills the form, so the operator can amend
// anything before submitting. The form then posts the fields — not the file —
// so no separate edit round-trip is needed. (The server still accepts a
// multipart upload too, e.g. from the API.) Needs installed tables to insert
// into; otherwise it points the operator at Utilities. Any action message is
// shown above. Wrapped in the shared admin layout (narrow). Returns HTML string.
//
// Parameters:
//   $settings - settings array
//   $tables_installed - bool, whether all tables are installed
//   $message - string|false, optional action-result message to display
//   $csrf_token - string, per-session token embedded in the form

/** @param PhoenixSettings $settings */
function view_admin_add_html(array $settings, bool $tables_installed, string|false $message, string $csrf_token): string
{
    require_once __DIR__.'/html.admin.layout.php';

    $body = '';

    if ($message) {
        $body .= '<div class="alert alert-info"><span class="ph-ico" data-lucide="info"></span><div>'.htmlspecialchars($message).'</div></div>';
    }

    if (! $tables_installed) {
        $body .= '<div class="alert alert-danger"><span class="ph-ico" data-lucide="triangle-alert"></span><div>The database is not installed yet. Install it from <a href="?page=utilities">Utilities</a> before adding torrents.</div></div>';

        return view_admin_layout_html($settings, 'Add a Torrent', $body, 'add', $csrf_token, 'Tracker', '', true);
    }

    // Static page body — self-contained markup in src/partials/admin.add.body.html
    // (HTML-/a11y-lintable). The only dynamic value is the CSRF token, echoed
    // inline; appended to $body (after any message) for the layout.
    ob_start();
    include __DIR__.'/../partials/admin.add.body.html';
    $body .= (string) ob_get_clean();

    // Drag/drop parsing + form-fill lives in assets/add.js, which uses
    // PhoenixTorrent (assets/torrent-parse.js); both load as page sources.
    $actions = '<a class="btn btn-secondary btn-sm" href="?page=upload"><span class="ph-ico" data-lucide="upload"></span>Bulk upload</a>';

    return view_admin_layout_html($settings, 'Add a Torrent', $body, 'add', $csrf_token, 'Tracker', $actions, true, '', '', ['/assets/torrent-parse.js', '/assets/add.js']);
}
