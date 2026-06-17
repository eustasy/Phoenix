<?php

declare(strict_types=1);

////	view_admin_upload_html
// Render the admin Bulk Upload page: pick (or drop) many .torrent files, or a
// whole folder, and each is POSTed straight to the add API in the browser — no
// per-file form. The uploads use the admin session, so they carry the CSRF
// token the API's session path requires; without an admin password set there is
// no session/token, so the page explains that instead. Needs installed tables
// (the API would fail otherwise). Marks the Add nav active. Wrapped in the
// shared admin layout (narrow). Returns HTML string.
//
// Parameters:
//   $settings - settings array
//   $tables_installed - bool, whether all tables are installed
//   $csrf_token - per-session token; '' when no admin password is configured

/** @param PhoenixSettings $settings */
function view_admin_upload_html(array $settings, bool $tables_installed, string $csrf_token): string
{
    require_once __DIR__.'/html.admin.layout.php';

    $back = '<a class="btn btn-secondary btn-sm" href="?page=add"><span class="ph-ico" data-lucide="file-plus"></span>Single add</a>';

    if (! $tables_installed) {
        $body = '<div class="alert alert-danger"><span class="ph-ico" data-lucide="triangle-alert"></span><div>The database is not installed yet. Install it from <a href="?page=utilities">Utilities</a> before adding torrents.</div></div>';

        return view_admin_layout_html($settings, 'Bulk Upload', $body, 'add', $csrf_token, 'Tracker', $back, true);
    }

    // The uploads post to the authenticated add API, which accepts an admin
    // session only with a CSRF token — and that exists only with an admin
    // password set. Without one, point the operator at the alternatives.
    if ($csrf_token === '') {
        $body = '<div class="alert alert-warning"><span class="ph-ico" data-lucide="shield-alert"></span><div>Bulk upload sends each file to the authenticated add API, which needs a session token. Set an <strong>admin password</strong> on the <a href="?page=settings">Settings</a> page to enable it &mdash; or script <code>POST /api/torrent/add</code> with an API key instead.</div></div>';

        return view_admin_layout_html($settings, 'Bulk Upload', $body, 'add', $csrf_token, 'Tracker', $back, true);
    }

    // Static page body — self-contained markup in src/partials/admin.upload.body.html
    // (HTML-/a11y-lintable). The only dynamic value is the CSRF token, echoed
    // inline; captured into $body for the layout.
    ob_start();
    include __DIR__.'/../partials/admin.upload.body.html';
    $body = (string) ob_get_clean();

    // The bulk-upload queue lives in assets/upload.js; it reads the CSRF token
    // from #bulk[data-csrf], so no PHP data needs inlining.
    return view_admin_layout_html($settings, 'Bulk Upload', $body, 'add', $csrf_token, 'Tracker', $back, true, '', '', ['/assets/upload.js']);
}
