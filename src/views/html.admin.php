<?php

declare(strict_types=1);

////	view_admin_html
// Render the admin panel's dashboard page as a set of self-contained panels
// (tracker stats, server-support diagnostics, utilities, add-a-torrent),
// wrapped in the shared admin layout (chrome, version line, logout form,
// navigation). The layout owns the full HTML document; this view builds only
// the Dashboard body and hands it off.
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
//   $stats - array<string,int>|false, merged tracker stats (seeders, leechers,
//            peers, torrents, downloads, traffic) plus 'registered' (total
//            torrents). False hides the stats block (e.g. tables not installed).
//   $tasks - array<string,int>, maintenance task name => last-run Unix
//            timestamp, for the "Last cleaned/optimized/…" lines.

/**
 * @param PhoenixSettings $settings
 * @param array<string, float|int|string|null>|false $database_size
 * @param array<string, int>|false $stats
 * @param array<string, int> $tasks
 */
function view_admin_html(array $settings, bool $tables_installed, array|false $database_size, string|false $message = false, bool $show_installed = false, string $csrf_token = '', ?string $php_version = null, ?bool $has_mysqli = null, array|false $stats = false, array $tasks = []): string
{
    // Hidden field carrying the CSRF token, embedded in every state-changing
    // form. Escaped defensively even though the token is always hex.
    $csrf_field = '<input type="hidden" name="csrf" value="'.htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8').'">';

    // Composer enforces ^8.2 and ext-mysqli, but the project supports manual
    // installs that bypass composer, so the runtime checks below stay in
    // place; tests pass overrides to reach the failure branches.
    $php_version ??= PHP_VERSION;
    $has_mysqli ??= class_exists('mysqli');

    ////	Tracker stats panel
    // Already-computed aggregates; number_format() matches the DB-size
    // formatting below. Rendered first, and only when the controller supplies
    // stats (i.e. tables are installed).
    $stats_panel = '';
    if ($stats !== false) {
        $stats_body = '<h1>Tracker Stats</h1>
		<p class="box background-clouds">Seeders '.number_format($stats['seeders']).' &middot; Leechers '.number_format($stats['leechers']).' &middot; Peers '.number_format($stats['peers']).'</p>
		<p class="box background-clouds">Registered torrents '.number_format($stats['registered'] ?? 0).' &middot; With active peers '.number_format($stats['torrents']).'</p>
		<p class="box background-clouds">Completed downloads '.number_format($stats['downloads']).' &middot; Traffic '.number_format($stats['traffic']).' bytes</p>';

        // Last-run timestamp for each maintenance task that has ever run.
        $task_labels = [
            'install' => 'Last installed',
            'migrate' => 'Last migrated',
            'clean' => 'Last cleaned',
            'optimize' => 'Last optimized',
        ];
        foreach ($task_labels as $task_name => $label) {
            if (isset($tasks[$task_name])) {
                $stats_body .= '
		<p class="color-asbestos">'.$label.': '.date('Y-m-d H:i', $tasks[$task_name]).'</p>';
            }
        }

        $stats_panel = '<div class="panel">'.$stats_body.'</div>';
    }

    ////	Server-support panel
    // PHP + MySQL diagnostics. Always rendered, even when mysqli is missing
    // (the warning lives here); the actionable panels below are gated on it.

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

    // MySQL support check + tables status
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
    }

    $server_support_panel = '<div class="panel"><h1>Compatibility Check</h1>
	'.$installed_html.'
	'.$php_compat_html.'
	'.$mysql_html.'</div>';

    ////	Utilities panel
    // Maintenance forms. Only meaningful when MySQL is available (every action
    // hits the DB); the setup form also shows when tables are missing so the
    // operator can install, while clean/optimize/migrate require live tables.
    $utilities_panel = '';
    if ($has_mysqli) {
        $utilities_html = '<h1>Utilities</h1>';

        // Message
        if ($message) {
            $utilities_html .= '<div class="box background-wisteria color-clouds"><h3>'.htmlspecialchars($message).'</h3></div>';
        }

        // Setup/Reset form
        if ($settings['db_reset'] || ! $tables_installed) {
            $utilities_html .= '<form class="mysql" action="" method="POST">
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
            $utilities_html .= '<p class="text-left color-asbestos">Install, Upgrade, and Reset
				<span class="button background-clouds float-right">Disabled</span></p>
				<div class="clear"></div>';
        }

        // Clean and Optimize forms (only if tables are installed)
        if ($tables_installed) {
            $utilities_html .= '<form class="mysql" action="" method="POST">
					<p class="float-left text-left">Clean out redundant peers</p>
					<input type="hidden" name="process" value="clean">'.$csrf_field.'
					<input class="button background-belize-hole color-clouds float-right p-like" type="submit" name="submit" value="Clean">
					<div class="clear"></div>
				</form>';
            $utilities_html .= '<form class="mysql" action="" method="POST">
					<p class="float-left text-left">Check, Analyze, Repair, and Optimize</p>
					<input type="hidden" name="process" value="optimize">'.$csrf_field.'
					<input class="button background-belize-hole color-clouds float-right p-like" type="submit" name="submit" value="Optimize">
					<div class="clear"></div>
				</form>';
            $utilities_html .= '<form class="mysql" action="" method="POST">
					<p class="float-left text-left">Apply idempotent schema migrations</p>
					<input type="hidden" name="process" value="migrate">'.$csrf_field.'
					<input class="button background-belize-hole color-clouds float-right p-like" type="submit" name="submit" value="Upgrade Schema">
					<div class="clear"></div>
				</form>';
        }

        $utilities_panel = '<div class="panel">'.$utilities_html.'</div>';
    }

    ////	Add-a-torrent panel
    // enctype is multipart so the .torrent file input rides along; the parsed
    // file supplies the base for every field, with any explicit field
    // overriding it (see admin_torrent_add_action). No "mysql" class so the
    // maintenance forms' double-submit guard never interferes with the upload.
    // Only when MySQL is available and the tables exist to insert into.
    $add_torrent_panel = '';
    if ($has_mysqli && $tables_installed) {
        $add_torrent_panel = '<div class="panel"><h1>Add a Torrent</h1>
				<form action="" method="POST" enctype="multipart/form-data">
					<p class="text-left">Name<br><input type="text" name="name"></p>
					<p class="text-left">Info Hash<br><input type="text" name="info_hash"></p>
					<p class="text-left">Size (bytes)<br><input type="number" name="size"></p>
					<p class="text-left"><input type="checkbox" name="listed" value="1" checked> Listed on the public index</p>
					<p class="text-left">Filename<br><input type="text" name="filename"></p>
					<p class="text-left">Files (JSON)<br><textarea name="files"></textarea></p>
					<p class="text-left">Trackers (one per line)<br><textarea name="trackers"></textarea></p>
					<p class="text-left">Web Seeds (one per line)<br><textarea name="webseeds"></textarea></p>
					<p class="text-left">Or drag &amp; drop / choose a .torrent file<br>
						<span id="torrent-drop" style="display:inline-block;border:2px dashed #bdc3c7;border-radius:.3em;padding:1em;cursor:pointer"><input type="file" name="torrent" id="torrent-file" accept=".torrent,application/x-bittorrent"><span id="torrent-drop-hint"></span></span>
					</p>
					<script>
					(function () {
						var zone = document.getElementById("torrent-drop");
						var input = document.getElementById("torrent-file");
						var hint = document.getElementById("torrent-drop-hint");
						if (!zone || !input) { return; }
						var paint = function (c) { zone.style.borderColor = c; };
						["dragenter", "dragover"].forEach(function (ev) {
							zone.addEventListener(ev, function (e) { e.preventDefault(); paint("#3498db"); });
						});
						["dragleave", "drop"].forEach(function (ev) {
							zone.addEventListener(ev, function (e) { e.preventDefault(); paint("#bdc3c7"); });
						});
						zone.addEventListener("drop", function (e) {
							if (e.dataTransfer && e.dataTransfer.files.length) {
								input.files = e.dataTransfer.files;
								if (hint) { hint.textContent = " " + input.files[0].name; }
							}
						});
						input.addEventListener("change", function () {
							if (hint) { hint.textContent = input.files.length ? " " + input.files[0].name : ""; }
						});
					})();
					</script>
					<input type="hidden" name="process" value="torrent_add">'.$csrf_field.'
					<input class="button background-belize-hole color-clouds float-right p-like" type="submit" name="submit" value="Add Torrent">
					<div class="clear"></div>
				</form></div>';
    }

    // Assemble the dashboard from its panels; the layout supplies the
    // surrounding page chrome (head, version line, logout form, navigation).
    $body = $stats_panel.$server_support_panel.$utilities_panel.$add_torrent_panel;

    require_once __DIR__.'/html.admin.layout.php';

    return view_admin_layout_html($settings, 'Dashboard', $body, 'dashboard', $csrf_token);
}
