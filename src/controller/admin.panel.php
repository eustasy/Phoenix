<?php

declare(strict_types=1);

////	admin_panel_controller
// Drives the authenticated admin panel: parses any submitted action,
// dispatches to the matching admin_*_action helper, queries the
// tables-installed flag and database size, and renders the panel HTML.
// Returns the rendered HTML string. Caller is responsible for echoing
// and exiting.

function admin_panel_controller(mysqli $connection, array $settings, int $time): string {
	$process = '';
	if (!empty($_POST['process'])) {
		// $process is only ever compared against literal action names below,
		// but htmlentities-ing it keeps any reflection of the value into
		// HTML safe should a future render path emit it.
		$process = htmlentities($_POST['process'], ENT_QUOTES, 'UTF-8');
	}

	require_once __DIR__.'/../model/db.tables.installed.php';
	$tables_installed = db_tables_installed($connection, $settings);

	$message = false;
	if ($process === 'setup') {
		require_once __DIR__.'/admin.setup.php';
		$result = admin_setup_action($connection, $settings, $time, $tables_installed);
		if ($result !== false) {
			$message          = $result;
			$tables_installed = true;
		}
	} elseif ($process === 'clean') {
		require_once __DIR__.'/admin.clean.php';
		$message = admin_clean_action($connection, $settings, $time);
	} elseif ($process === 'optimize') {
		require_once __DIR__.'/admin.optimize.php';
		$message = admin_optimize_action($connection, $settings, $time);
	}

	$database_size = false;
	if ($tables_installed) {
		require_once __DIR__.'/../model/db.size.php';
		$database_size = db_size($connection, $settings);
	}

	require_once __DIR__.'/../views/html.admin.php';
	return view_admin_html(
		$settings,
		$tables_installed,
		$database_size,
		$message,
		isset($_GET['installed'])
	);
}
