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
$cmd = 'mysqldump'
	. ' --allow-keywords'
	. ' --replace'
	. ' --routines'
	. ' --skip-add-drop-table'
	. ' --skip-lock-tables'
	. ' --single-transaction'
	. ' --triggers'
	. ' --tz-utc'
	. ' --ignore-table=' . escapeshellarg($settings['db_name'] . '.' . $settings['db_prefix'] . 'peers')
	. ' -u' . escapeshellarg($settings['db_user'])
	. ' -p' . escapeshellarg($settings['db_pass'])
	. ' '   . escapeshellarg($settings['db_name'])
	. ' > ' . escapeshellarg($filepath);

exec($cmd, $output, $exit_code);

if ( $exit_code !== 0 ) {
	echo 'Backup failed.' . PHP_EOL;
	exit(1);
}

// Rotate: delete the oldest backups beyond the configured limit.
if ( !empty($settings['backup_rotate']) && intval($settings['backup_rotate']) > 0 ) {
	$backups = glob($backup_dir . $settings['db_name'] . '.*.sql');
	if ( $backups ) {
		rsort($backups);
		foreach ( array_slice($backups, intval($settings['backup_rotate'])) as $old ) {
			unlink($old);
		}
	}
}
