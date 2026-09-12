<?php

declare(strict_types=1);

////	view_admin_backups_html
// Render the admin Backups page: a "Run backup now" button (CSRF-protected
// POST) and a table of the existing backups (name, total size, created time),
// each listing its individual dumps as separate downloads — a backup is a dated
// directory holding schema.sql plus one file per table, so restoring one table
// means downloading one file. Legacy single-file dumps list as one download.
// The environment note flags the mysqldump / writable-directory requirement, and
// any action message (a success line or the engine's error) is shown above.
// Wrapped in the shared admin layout. Returns HTML string.

/**
 * @param PhoenixSettings $settings
 * @param list<array{name: string, size: int, mtime: int, files: list<array{name: string, size: int}>}> $backups
 */
function view_admin_backups_html(array $settings, array $backups, string|false $message, string $csrf_token): string
{
    require_once __DIR__.'/html.admin.layout.php';
    require_once __DIR__.'/../functions/format.bytes.php';

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
    $body .= '<p class="muted mt-0 text-sm">Backups require the <code>mysqldump</code> binary, <code>proc_open</code>, and a writable backup directory available to the web-server user. Each backup is a directory holding <code>schema.sql</code> and one data file per table, gzipped as written unless <code>backup_compress</code> is off. Import <code>schema.sql</code> first, then whichever tables you want back &mdash; <code>task_runs</code> is maintenance history nothing reads, and <code>peers</code> repopulates itself.</p>';

    if ($backups === []) {
        $body .= '<div class="ph-empty"><span class="ph-ico" data-lucide="archive"></span><p>No backups yet.</p></div>';
    } else {
        $rows = '';
        foreach ($backups as $backup) {
            $link = '?page=backups&amp;download='.urlencode($backup['name']);

            // Legacy single-file dump: the entry is itself the download.
            $downloads = $backup['files'] === []
                ? '<a class="btn btn-ghost btn-xs" href="'.$link.'"><span class="ph-ico" data-lucide="download"></span>Download</a>'
                : '';
            foreach ($backup['files'] as $file) {
                $downloads .= '<a class="btn btn-ghost btn-xs" href="'.$link.'&amp;file='.urlencode($file['name']).'" title="'.
                    htmlspecialchars($file['name'].' — '.format_bytes($file['size']), ENT_QUOTES, 'UTF-8').'">'.
                    '<span class="ph-ico" data-lucide="download"></span>'.htmlspecialchars(preg_replace('/\.sql(\.gz)?$/', '', $file['name']) ?? $file['name']).
                    '</a>';
            }

            $rows .= '<tr>'.
                '<td class="mono text-xs">'.htmlspecialchars($backup['name']).'</td>'.
                '<td class="table-col-numeric mono" data-sort="'.$backup['size'].'">'.format_bytes($backup['size']).'</td>'.
                '<td class="mono muted">'.date('Y-m-d H:i', $backup['mtime']).'</td>'.
                '<td><div class="row-actions">'.$downloads.'</div></td>'.
                '</tr>';
        }
        $body .= '<div class="ph-card-table">'.
            '<table><thead><tr><th>Backup</th><th class="table-col-numeric">Size</th><th>Created</th><th class="tar">Download</th></tr></thead>'.
            '<tbody>'.$rows.'</tbody></table></div>';
    }

    return view_admin_layout_html($settings, 'Backups', $body, 'backups', $csrf_token, 'Server', $actions);
}
