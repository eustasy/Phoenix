<?php

declare(strict_types=1);

////	view_admin_backups_html
// Render the admin Backups page: a "Run backup now" button (CSRF-protected
// POST) and a table of the existing dumps (file name, size, created time). The
// environment note flags the mysqldump / writable-directory requirement, and
// any action message (a success line or the engine's error) is shown above.
// Wrapped in the shared admin layout (wide variant). Returns HTML string.

/**
 * @param PhoenixSettings $settings
 * @param list<array{name: string, size: int, mtime: int}> $backups
 */
function view_admin_backups_html(array $settings, array $backups, string|false $message, string $csrf_token): string
{
    $csrf_field = '<input type="hidden" name="csrf" value="'.htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8').'">';

    $body = '<h1>Backups</h1>';

    if ($message) {
        $body .= '<div class="box background-wisteria color-clouds"><h3>'.htmlspecialchars($message).'</h3></div>';
    }

    // Environment caveat (the run fails with a clear message when unmet).
    $body .= '<p class="color-asbestos">Backups require the <code>mysqldump</code> binary, '.
        '<code>proc_open</code>, and a writable backup directory available to the web-server user.</p>';

    // Run-now button. class="mysql" so the layout's double-submit guard disables
    // it on submit (a dump can take a while).
    $body .= '<form class="mysql" method="POST">'.
        '<input type="hidden" name="process" value="backup">'.$csrf_field.
        '<input class="button background-belize-hole color-clouds" type="submit" name="submit" value="Run backup now">'.
        '</form>';

    if ($backups === []) {
        $body .= '<p class="box background-clouds">No backups yet.</p>';
    } else {
        $rows = '';
        foreach ($backups as $backup) {
            $rows .= '<tr>'.
                '<td><code>'.htmlspecialchars($backup['name']).'</code></td>'.
                '<td>'.number_format($backup['size']).' bytes</td>'.
                '<td>'.date('Y-m-d H:i', $backup['mtime']).'</td>'.
                '<td><a href="?page=backups&amp;download='.urlencode($backup['name']).'">Download</a></td>'.
                '</tr>';
        }
        $body .= '<table class="data-table">'.
            '<thead><tr><th>File</th><th>Size</th><th>Created</th><th>Actions</th></tr></thead>'.
            '<tbody>'.$rows.'</tbody></table>';
    }

    require_once __DIR__.'/html.admin.layout.php';

    return view_admin_layout_html($settings, 'Backups', $body, 'backups', $csrf_token, true);
}
