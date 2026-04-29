<?php

// Scheduled database backup with optional rotation.
require_once __DIR__.'/../../src/phoenix.php';

$backup_dir = !empty($settings['backup_dir'])
	? rtrim($settings['backup_dir'], '/') . '/'
	: $settings['root'] . 'backups/';

if ( !is_dir($backup_dir) ) {
	echo 'BACKUP_DIR_NOT_FOUND' . PHP_EOL;
	exit(1);
}

$filename = $settings['db_name'] . '.' . date('Ymd_Hi') . '.sql';
$filepath = $backup_dir . $filename;

// Peers are ephemeral (expire after 3x announce_interval) and can be recreated
// by running Setup in admin.php, so there is no value in backing up their rows.

// Write credentials to a private temp file so the password never appears in
// the process list. once.db.connect mutates db_host in place (prepends 'p:'
// for persistent connections), so strip that prefix before writing.
$db_host  = (strncmp($settings['db_host'], 'p:', 2) === 0)
	? substr($settings['db_host'], 2)
	: $settings['db_host'];
$cnf_file = tempnam(sys_get_temp_dir(), 'phxbak_');
if ( !chmod($cnf_file, 0600) ) {
	unlink($cnf_file);
	echo 'Backup failed: could not secure credentials file.' . PHP_EOL;
	exit(1);
}
file_put_contents($cnf_file,
	'[client]' . PHP_EOL .
	'host='     . $db_host . PHP_EOL .
	'user='     . $settings['db_user'] . PHP_EOL .
	'password="' . str_replace('"', '""', $settings['db_pass']) . '"' . PHP_EOL
);

// proc_open with an argument array bypasses the shell entirely, so no
// escaping is needed and settings values cannot be misinterpreted as flags.
$errfile = $filepath . '.err';
$proc = proc_open(
	[
		'mysqldump',
		'--defaults-extra-file=' . $cnf_file,
		'--allow-keywords',
		'--replace',
		'--routines',
		'--skip-add-drop-table',
		'--skip-lock-tables',
		'--single-transaction',
		'--triggers',
		'--tz-utc',
		'--ignore-table=' . $settings['db_name'] . '.' . $settings['db_prefix'] . 'peers',
		$settings['db_name'],
	],
	[
		0 => ['pipe', 'r'],
		1 => ['file', $filepath, 'w'],
		2 => ['file', $errfile,  'w'],
	],
	$pipes
);

if ( $proc === false ) {
	if ( !unlink($cnf_file) ) {
		echo 'Warning: could not remove credentials file ' . $cnf_file . PHP_EOL;
	}
	echo 'Backup failed: could not start mysqldump.' . PHP_EOL;
	exit(1);
}

fclose($pipes[0]);
$exit_code = proc_close($proc);

if ( !unlink($cnf_file) ) {
	echo 'Warning: could not remove credentials file ' . $cnf_file . PHP_EOL;
}

if ( $exit_code !== 0 ) {
	$error = is_readable($errfile) ? trim(file_get_contents($errfile)) : '';
	echo 'Backup failed.' . ( $error ? ' ' . $error : '' ) . PHP_EOL;
	exit(1);
}
if ( !unlink($errfile) ) {
	echo 'Warning: could not remove error file ' . $errfile . PHP_EOL;
}

// Rotate: delete backups older than backup_rotate days.
if ( intval($settings['backup_rotate']) > 0 ) {
	$cutoff = $time - ( intval($settings['backup_rotate']) * 86400 );
	$backups = glob($backup_dir . $settings['db_name'] . '.*.sql');
	if ( $backups ) {
		foreach ( $backups as $old ) {
			$mtime = filemtime($old);
			if ( $mtime !== false && $mtime < $cutoff && !unlink($old) ) {
				echo 'Warning: could not remove old backup ' . basename($old) . PHP_EOL;
			}
		}
	}
}
