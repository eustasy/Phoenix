<?php

declare(strict_types=1);

////	view_admin_utilities_html
// Render the admin Utilities page: the database setup/reset form plus the
// clean, optimize, and schema-migrate maintenance forms. The setup form also
// appears when the tables are missing (so the operator can install); the
// clean/optimize/migrate forms need live tables. Any action message is shown
// above. Wrapped in the shared admin layout. Returns HTML string.
//
// Parameters:
//   $settings - settings array (uses db_reset)
//   $tables_installed - bool, whether all tables are installed
//   $message - string|false, optional action-result message to display
//   $csrf_token - string, per-session token embedded in every form

/** @param PhoenixSettings $settings */
function view_admin_utilities_html(array $settings, bool $tables_installed, string|false $message, string $csrf_token): string
{
    // Hidden field carrying the CSRF token, embedded in every state-changing
    // form. Escaped defensively even though the token is always hex.
    $csrf_field = '<input type="hidden" name="csrf" value="'.htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8').'">';

    $body = '<h1>Utilities</h1>';

    // Message
    if ($message) {
        $body .= '<div class="box background-wisteria color-clouds"><h3>'.htmlspecialchars($message).'</h3></div>';
    }

    // Setup/Reset form. Posts back to this page (an empty action would too, but
    // be explicit so the query string is never lost).
    if ($settings['db_reset'] || ! $tables_installed) {
        $body .= '<form class="mysql" action="?page=utilities" method="POST">
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
        $body .= '<p class="text-left color-asbestos">Install, Upgrade, and Reset
			<span class="button background-clouds float-right">Disabled</span></p>
			<div class="clear"></div>';
    }

    // Clean, Optimize, and Migrate forms (only if tables are installed)
    if ($tables_installed) {
        $body .= '<form class="mysql" action="?page=utilities" method="POST">
				<p class="float-left text-left">Clean out redundant peers</p>
				<input type="hidden" name="process" value="clean">'.$csrf_field.'
				<input class="button background-belize-hole color-clouds float-right p-like" type="submit" name="submit" value="Clean">
				<div class="clear"></div>
			</form>';
        $body .= '<form class="mysql" action="?page=utilities" method="POST">
				<p class="float-left text-left">Check, Analyze, Repair, and Optimize</p>
				<input type="hidden" name="process" value="optimize">'.$csrf_field.'
				<input class="button background-belize-hole color-clouds float-right p-like" type="submit" name="submit" value="Optimize">
				<div class="clear"></div>
			</form>';
        $body .= '<form class="mysql" action="?page=utilities" method="POST">
				<p class="float-left text-left">Apply idempotent schema migrations</p>
				<input type="hidden" name="process" value="migrate">'.$csrf_field.'
				<input class="button background-belize-hole color-clouds float-right p-like" type="submit" name="submit" value="Upgrade Schema">
				<div class="clear"></div>
			</form>';
    }

    require_once __DIR__.'/html.admin.layout.php';

    return view_admin_layout_html($settings, 'Utilities', $body, 'utilities', $csrf_token);
}
