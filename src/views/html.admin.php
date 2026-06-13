<?php

declare(strict_types=1);

////	view_admin_html
// Render the admin panel's dashboard page: the diagnostics and utilities
// body, wrapped in the shared admin layout (chrome, version line, logout
// form, navigation). The layout owns the full HTML document; this view
// builds only the Dashboard body and hands it off.
// Returns HTML string. Caller is responsible for echo and exit.
//
// Parameters:
//   $settings - settings array
//   $tables_installed - bool, whether all tables are installed
//   $database_size - array|false, database size info (Data, Indexes, Total, Free)
//   $message - string|false, optional message to display
//   $show_installed - bool, whether to show "Installation complete" message
//   $csrf_token - string, per-session token embedded in every form (empty when
//                 no admin_password is set, since CSRF is not enforced then).
//   $php_version - string|null, PHP version to report (defaults to PHP_VERSION).
//                  Override only used by tests so the unsupported-version
//                  branch can be exercised without spawning a different PHP.
//   $has_mysqli - bool|null, whether mysqli is available (defaults to
//                 class_exists('mysqli')). Override only used by tests so
//                 the missing-extension branch can be exercised.

/**
 * @param PhoenixSettings $settings
 * @param array<string, float|int|string|null>|false $database_size
 */
function view_admin_html(array $settings, bool $tables_installed, array|false $database_size, string|false $message = false, bool $show_installed = false, string $csrf_token = '', ?string $php_version = null, ?bool $has_mysqli = null): string
{
    // Hidden field carrying the CSRF token, embedded in every state-changing
    // form. Escaped defensively even though the token is always hex.
    $csrf_field = '<input type="hidden" name="csrf" value="'.htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8').'">';

    // Composer enforces ^8.2 and ext-mysqli, but the project supports manual
    // installs that bypass composer, so the runtime checks below stay in
    // place; tests pass overrides to reach the failure branches.
    $php_version ??= PHP_VERSION;
    $has_mysqli ??= class_exists('mysqli');

    // Build installation complete message
    $installed_html = '';
    if ($show_installed) {
        $installed_html = '<p class="box background-green-sea color-clouds">Installation complete.</p>';
    }

    // PHP version check
    if (version_compare($php_version, '8.2.0', '>=')) {
        $php_compat_html = '<p class="box background-green-sea color-clouds">Your PHP version is supported.</p>
		<p class="color-asbestos">PHP Version: '.$php_version.'</p>';
    } else {
        $php_compat_html = '<p class="box background-pomegranate color-clouds">Phoenix requires PHP &gt;= 8.2.</p>
		<p class="color-asbestos">PHP Version: '.$php_version.'</p>';
    }

    // MySQL support check
    $mysql_html = '';
    if (! $has_mysqli) {
        $mysql_html = '<p class="box background-pomegranate color-clouds">Your server does not support MySQL.</p>';
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
        $mysql_html = '<p class="box background-green-sea color-clouds">Your server supports MySQL.</p>
		<p class="color-asbestos">MySQL Version: '.$mysql_version.'</p>';

        // Tables status
        if ($tables_installed) {
            $mysql_html .= '<p class="box background-green-sea color-clouds">All your tables are installed.';
            if ($database_size) {
                $mysql_html .= ' Their current size is '.number_format((float) ($database_size['Total'] ?? 0)).' bytes.';
            }
            $mysql_html .= '</p>';
        } else {
            $mysql_html .= '<p class="box background-pomegranate color-clouds">Some or all of your tables are not installed.</p>';
        }

        // Utilities section
        $mysql_html .= '<br><h1>Utilities</h1>';

        // Message
        if ($message) {
            $mysql_html .= '<div class="box background-wisteria color-clouds"><h3>'.htmlspecialchars($message).'</h3></div>';
        }

        // Setup/Reset form
        if ($settings['db_reset'] || ! $tables_installed) {
            $mysql_html .= '<form class="mysql" action="" method="POST">
				<p class="box background-pomegranate color-clouds">You should set
				<code>$settings[\'db_reset\']</code>
				to false to disable resets,<br>
				or delete <code>public/admin.php</code> when you\'re up and running.</p>
				<p class="float-left text-left">Install, Upgrade, and Reset</p>
				<input type="hidden" name="process" value="setup">'.$csrf_field.'
				<input class="button background-belize-hole color-clouds float-right" type="submit" name="submit" value="Setup">
				<div class="clear"></div>
			</form>';
        } else {
            $mysql_html .= '<p class="text-left color-asbestos">Install, Upgrade, and Reset
				<span class="button background-clouds float-right">Disabled</span></p>
				<div class="clear"></div>';
        }

        // Clean and Optimize forms (only if tables are installed)
        if ($tables_installed) {
            $mysql_html .= '<form class="mysql" action="" method="POST">
					<p class="float-left text-left">Clean out redundant peers</p>
					<input type="hidden" name="process" value="clean">'.$csrf_field.'
					<input class="button background-belize-hole color-clouds float-right p-like" type="submit" name="submit" value="Clean">
					<div class="clear"></div>
				</form>';
            $mysql_html .= '<form class="mysql" action="" method="POST">
					<p class="float-left text-left">Check, Analyze, Repair, and Optimize</p>
					<input type="hidden" name="process" value="optimize">'.$csrf_field.'
					<input class="button background-belize-hole color-clouds float-right p-like" type="submit" name="submit" value="Optimize">
					<div class="clear"></div>
				</form>';
            $mysql_html .= '<form class="mysql" action="" method="POST">
					<p class="float-left text-left">Apply idempotent schema migrations</p>
					<input type="hidden" name="process" value="migrate">'.$csrf_field.'
					<input class="button background-belize-hole color-clouds float-right p-like" type="submit" name="submit" value="Upgrade Schema">
					<div class="clear"></div>
				</form>';
        }
    }

    // Assemble the dashboard body; the layout supplies the surrounding page
    // chrome (head, version line, logout form, navigation).
    $body = '<h1>Compatibility Check</h1>
	'.$installed_html.'
	'.$php_compat_html.'
	'.$mysql_html;

    require_once __DIR__.'/html.admin.layout.php';

    return view_admin_layout_html($settings, 'Dashboard', $body, 'dashboard', $csrf_token);
}
