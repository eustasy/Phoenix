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
 */
function view_admin_support_html(array $settings, bool $tables_installed, array|false $database_size, string $csrf_token = '', ?string $php_version = null, ?bool $has_mysqli = null): string
{
    require_once __DIR__.'/html.admin.layout.php';

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
                $size_note = ' <span class="dim">Their current size is '.number_format((float) ($database_size['Total'] ?? 0)).' bytes.</span>';
            }
            $mysql_html .= '<div class="alert alert-success alert-center"><span class="ph-ico" data-lucide="database"></span>All your tables are installed.'.$size_note.'</div>';
        } else {
            $mysql_html .= '<div class="alert alert-danger alert-center"><span class="ph-ico" data-lucide="circle-alert"></span>Some or all of your tables are not installed. Install them from <a href="?page=utilities">Utilities</a>.</div>';
        }
    }

    $body = $php_compat_html.$mysql_html.
        '<p class="muted text-sm">Read-only diagnostics. Phoenix requires PHP &ge; 8.2 and a MySQL-compatible database.</p>';

    return view_admin_layout_html($settings, 'Server Support', $body, 'support', $csrf_token, 'Server', '', true);
}
