<?php

declare(strict_types=1);

////	view_admin_support_html
// Render the admin Server Support page: a read-only compatibility report —
// PHP version, MySQL/ext-mysqli availability and client version, the
// installed-tables check, and the database size. No forms. Wrapped in the
// shared admin layout (narrow). Returns HTML string.
//
// Parameters:
//   $settings - settings array
//   $tables_installed - bool, whether all tables are installed
//   $database_size - array|false, database size info (Data, Indexes, Total, Free)
//   $csrf_token - string, per-session token for the layout's logout form
//   $php_version - string|null, PHP version to report (defaults to PHP_VERSION).
//                  Override only used by tests so the unsupported-version
//                  branch can be exercised without spawning a different PHP.
//   $has_mysqli - bool|null, whether mysqli is available (defaults to
//                 class_exists('mysqli')). Override only used by tests so the
//                 missing-extension branch can be exercised.

/**
 * @param PhoenixSettings $settings
 * @param array<string, float|int|string|null>|false $database_size
 * @param array{
 *     geo: array{enabled: bool, reader: string, database: string, readable: bool},
 *     sentry: array{installed: bool, dsn: bool, reporting: bool},
 *     totp: array{installed: bool, enabled: bool, password: bool},
 *     backups: array{requested: bool, available: bool},
 * }|null $extras
 */
function view_admin_support_html(array $settings, bool $tables_installed, array|false $database_size, string $csrf_token = '', ?string $php_version = null, ?bool $has_mysqli = null, ?array $extras = null): string
{
    require_once __DIR__.'/html.admin.layout.php';
    require_once __DIR__.'/../functions/format.bytes.php';

    // Composer enforces ^8.2 and ext-mysqli, but the project supports manual
    // installs that bypass composer, so the runtime checks below stay in place;
    // tests pass overrides to reach the failure branches.
    $php_version ??= PHP_VERSION;
    $has_mysqli ??= class_exists('mysqli');

    // PHP version check
    if (version_compare($php_version, '8.2.0', '>=')) {
        $php_compat_html = '<div class="alert alert-success alert-center"><span class="ph-ico" data-lucide="circle-check-big"></span>Your PHP version is supported. <span class="dim">PHP Version: '.htmlspecialchars($php_version).'</span></div>';
    } else {
        $php_compat_html = '<div class="alert alert-danger alert-center"><span class="ph-ico" data-lucide="circle-alert"></span>Phoenix requires PHP &gt;= 8.2. <span class="dim">PHP Version: '.htmlspecialchars($php_version).'</span></div>';
    }

    // MySQL support check + tables status
    if (! $has_mysqli) {
        $mysql_html = '<div class="alert alert-danger alert-center"><span class="ph-ico" data-lucide="circle-alert"></span>Your server does not support MySQL.</div>';
    } else {
        // mysqli_get_client_info typically returns "mysqlnd 8.x.y-…", but a
        // build without a '-' suffix is valid; strpos() returns false there
        // and substr() under strict_types refuses a false length.
        $mysql_version = mysqli_get_client_info();
        $dash = strpos($mysql_version, '-');
        $mysql_version = trim(
            $dash !== false ? substr($mysql_version, 0, $dash) : $mysql_version,
            'mysqlnd ',
        );
        $mysql_html = '<div class="alert alert-success alert-center"><span class="ph-ico" data-lucide="circle-check-big"></span>Your server supports MySQL. <span class="dim">MySQL Version: '.htmlspecialchars($mysql_version).'</span></div>';

        // Tables status
        if ($tables_installed) {
            $size_note = '';
            if ($database_size) {
                $size_note = ' <span class="dim">Their current size is '.format_bytes((int) ($database_size['Total'] ?? 0)).'.</span>';
            }
            $mysql_html .= '<div class="alert alert-success alert-center"><span class="ph-ico" data-lucide="database"></span>All your tables are installed.'.$size_note.'</div>';
        } else {
            $mysql_html .= '<div class="alert alert-danger alert-center"><span class="ph-ico" data-lucide="circle-alert"></span>Some or all of your tables are not installed. Install them from <a href="?page=utilities">Utilities</a>.</div>';
        }
    }

    ////	Optional extras
    // None of these is required, so an unconfigured one is stated plainly, not
    // flagged. Two cases DO warn: an admin password with no second factor, and
    // a setting asking for something the server cannot provide.
    $extras_html = '';
    if ($extras !== null) {
        $alert = static function (string $level, string $icon, string $text, string $note = ''): string {
            return '<div class="alert alert-'.$level.' alert-center"><span class="ph-ico" data-lucide="'.$icon.'"></span><div>'.
                $text.($note === '' ? '' : ' <span class="dim">'.$note.'</span>').'</div></div>';
        };

        ////	Two-factor
        $totp = $extras['totp'];
        if (! $totp['password']) {
            $extras_html .= $alert('warning', 'triangle-alert', 'The admin panel has no password.<br>', 'Anyone who can reach this page can use it. Set <code>admin_password</code>.');
        } elseif ($totp['enabled']) {
            $extras_html .= $alert('success', 'shield-check', 'Two-factor authentication is enabled.');
        } elseif (! $totp['installed']) {
            $extras_html .= $alert('warning', 'triangle-alert', 'Two-factor authentication is not available.<br>', 'The admin panel is password-only. Run <code>composer require eustasy/authenticatron</code> to add a second factor.');
        } else {
            $extras_html .= $alert('warning', 'triangle-alert', 'Two-factor authentication is off.<br>', 'The admin panel is password-only. Enable it from <a href="?page=settings">Settings</a>.');
        }

        ////	Backup compression
        $backups = $extras['backups'];
        if (! $backups['available']) {
            $level = $backups['requested'] ? 'danger' : 'info';
            $note = $backups['requested']
                ? '<code>backup_compress</code> is on, but backups will be written as plain SQL.'
                : 'Backups are written as plain SQL.';
            $extras_html .= $alert($level, $backups['requested'] ? 'circle-alert' : 'file-archive', 'PHP has no zlib support.', $note);
        } elseif ($backups['requested']) {
            $extras_html .= $alert('success', 'file-archive', 'Backups are compressed with gzip.', 'Roughly 10&times; smaller, via PHP&rsquo;s zlib.');
        } else {
            $extras_html .= $alert('info', 'file-archive', 'Backups are written as plain SQL.', 'Set <code>backup_compress</code> to gzip them.');
        }

        ////	Geo
        $geo = $extras['geo'];
        if (! $geo['enabled']) {
            $extras_html .= $alert('info', 'globe', 'Geo enrichment is off.', 'Set <code>stats_geo</code> to map peers and tag events by country.');
        } elseif ($geo['reader'] === 'missing') {
            $extras_html .= $alert('danger', 'circle-alert', 'Geo is on but the reader is not installed.<br>', 'Run <code>composer require maxmind-db/reader</code>.');
        } elseif (! $geo['readable']) {
            $extras_html .= $alert('danger', 'circle-alert', 'Geo is on but no database was found.<br>', 'Point <code>stats_geo_database</code> at a GeoLite2 <code>.mmdb</code>, or drop one in <code>/usr/share/GeoIP</code>, <code>/var/lib/GeoIP</code> or <code>config/</code>.');
        } else {
            $where = '<code>'.htmlspecialchars($geo['database'], ENT_QUOTES, 'UTF-8').'</code>';
            if ($geo['reader'] === 'extension') {
                $extras_html .= $alert('success', 'globe', 'Geo enrichment is active, using the <strong>C extension</strong>.<br>', 'Reading '.$where.'.');
            } else {
                // Works, but slowly, and it is on the announce path.
                $extras_html .= $alert('warning', 'triangle-alert', 'Geo enrichment is active, but using the <strong>pure-PHP reader</strong>.<br>', 'Around 40&times; slower than <code>ext-maxminddb</code>, on every announce. Install it (Debian/Ubuntu: <code>php-maxminddb</code>) and it is picked up automatically. Reading '.$where.'.');
            }
        }

        ////	Error reporting
        $sentry = $extras['sentry'];
        if (! $sentry['installed']) {
            $extras_html .= $alert('info', 'bug', 'Error reporting is not installed.<br>', 'Run <code>composer require sentry/sentry</code> to report failures to Sentry.');
        } elseif (! $sentry['reporting'] || ! $sentry['dsn']) {
            $missing = ! $sentry['reporting'] ? '<code>report_errors</code>' : '<code>sentry_dsn</code>';
            $extras_html .= $alert('info', 'bug', 'Sentry is installed but not reporting.<br>', 'Set '.$missing.' to turn it on.');
        } else {
            $extras_html .= $alert('success', 'bug', 'Errors are being reported to Sentry.');
        }

        $extras_html = '<div class="ph-section-head"><h3>Optional extras</h3>'.
            '<span class="dim text-sm">None of these is required to run a tracker</span></div>'.$extras_html;
    }

    $body = $php_compat_html.$mysql_html.$extras_html.
        '<p class="muted text-sm">Read-only diagnostics. Phoenix requires PHP &ge; 8.2 and a MySQL-compatible database.</p>';

    return view_admin_layout_html($settings, 'Server Support', $body, 'support', $csrf_token, 'Server', '', 'narrow');
}
