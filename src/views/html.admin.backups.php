<?php

declare(strict_types=1);

////	view_admin_backups_html
// Render the admin Backups page: a "Run backup now" button (CSRF-protected
// POST) and a table of the existing dumps (file name, size, created time). The
// environment note flags the mysqldump / writable-directory requirement, and
// any action message (a success line or the engine's error) is shown above.
// Wrapped in the shared admin layout. Returns HTML string.

/**
 * @param PhoenixSettings $settings
 * @param list<array{name: string, size: int, mtime: int}> $backups
 */
function view_admin_backups_html(array $settings, array $backups, string|false $message, string $csrf_token): string
{
    require_once __DIR__.'/html.admin.layout.php';

    $csrf_field = '<input type="hidden" name="csrf" value="'.htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8').'">';

    // Run-now button lives in the topbar. class="mysql" so the layout's
    // double-submit guard disables it on submit (a dump can take a while).
    $actions = '<form class="mysql m-0" method="POST">'.
        '<input type="hidden" name="process" value="backup">'.$csrf_field.
        '<button type="submit" name="submit" class="btn btn-primary btn-sm"><span class="ph-ico" data-lucide="play"></span>Run backup now</button>'.
        '</form>';

    $body = '';

    if ($message) {
        $body .= '<div class="alert alert-info"><span class="ph-ico" data-lucide="info"></span><div>'.htmlspecialchars($message).'</div></div>';
    }

    // Environment caveat (the run fails with a clear message when unmet).
    $body .= '<p class="muted mt-0 text-sm">Backups require the <code>mysqldump</code> binary, <code>proc_open</code>, and a writable backup directory available to the web-server user.</p>';

    if ($backups === []) {
        $body .= '<div class="ph-empty"><span class="ph-ico" data-lucide="archive"></span><p>No backups yet.</p></div>';
    } else {
        $rows = '';
        foreach ($backups as $backup) {
            $rows .= '<tr>'.
                '<td class="mono text-xs">'.htmlspecialchars($backup['name']).'</td>'.
                '<td class="table-col-numeric mono">'.number_format($backup['size']).' bytes</td>'.
                '<td class="mono muted">'.date('Y-m-d H:i', $backup['mtime']).'</td>'.
                '<td><div class="row-actions"><a class="btn btn-ghost btn-xs" href="?page=backups&amp;download='.urlencode($backup['name']).'"><span class="ph-ico" data-lucide="download"></span>Download</a></div></td>'.
                '</tr>';
        }
        $body .= '<div class="ph-card-table">'.
            '<table><thead><tr><th>File</th><th class="table-col-numeric">Size</th><th>Created</th><th class="tar">Actions</th></tr></thead>'.
            '<tbody>'.$rows.'</tbody></table></div>';
    }

    return view_admin_layout_html($settings, 'Backups', $body, 'backups', $csrf_token, 'Server', $actions, true);
}
