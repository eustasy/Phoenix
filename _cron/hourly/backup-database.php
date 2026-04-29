<?php

// Scheduled database backup with optional rotation.
require_once __DIR__.'/../../_phoenix.php';

$backup_dir = !empty($settings['backup_dir'])
	? rtrim($settings['backup_dir'], '/') . '/'
	: $settings['root'] . '_backups/';

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
chmod($cnf_file, 0600);
file_put_contents($cnf_file,
	'[client]' . PHP_EOL .
	'host='     . $db_host . PHP_EOL .
	'user='     . $settings['db_user'] . PHP_EOL .
	'password="' . str_replace('"', '""', $settings['db_pass']) . '"' . PHP_EOL
);

$errfile = $filepath . '.err';
$cmd = 'mysqldump'
	. ' --defaults-extra-file=' . escapeshellarg($cnf_file)
	. ' --allow-keywords'
	. ' --replace'
	. ' --routines'
	. ' --skip-add-drop-table'
	. ' --skip-lock-tables'
	. ' --single-transaction'
	. ' --triggers'
	. ' --tz-utc'
	. ' --ignore-table=' . escapeshellarg($settings['db_name'] . '.' . $settings['db_prefix'] . 'peers')
	. ' '   . escapeshellarg($settings['db_name'])
	. ' > ' . escapeshellarg($filepath)
	. ' 2>' . escapeshellarg($errfile);

exec($cmd, $output, $exit_code);
@unlink($cnf_file); // always removed — never leave credentials on disk

if ( $exit_code !== 0 ) {
	$error = is_readable($errfile) ? trim(file_get_contents($errfile)) : '';
	echo 'Backup failed.' . ( $error ? ' ' . $error : '' ) . PHP_EOL;
	exit(1);
}
@unlink($errfile);

// Rotate: delete backups older than backup_rotate days.
if ( intval($settings['backup_rotate']) > 0 ) {
	$cutoff = time() - ( intval($settings['backup_rotate']) * 86400 );
	$backups = glob($backup_dir . $settings['db_name'] . '.*.sql');
	if ( $backups ) {
		foreach ( $backups as $old ) {
			if ( filemtime($old) < $cutoff ) {
				unlink($old);
			}
		}
	}
}
