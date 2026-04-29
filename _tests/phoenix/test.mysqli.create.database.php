<?php

require_once $settings['functions'].'function.mysqli.create.database.php';

$result = create_database($connection, $settings);
if ( !$result ) {
	echo 'Error: create_database() returned false on first call.'.PHP_EOL;
	$failure = true;
}

// IF NOT EXISTS means a second call must be a no-op, not an error.
$result = create_database($connection, $settings);
if ( !$result ) {
	echo 'Error: create_database() returned false on second call (idempotency).'.PHP_EOL;
	$failure = true;
}

foreach ( array('peers', 'tasks', 'torrents') as $table ) {
	$check = mysqli_query($connection,
		'SELECT TABLE_NAME FROM `information_schema`.`TABLES` '.
		'WHERE TABLE_SCHEMA = \''.$settings['db_name'].'\' '.
		'AND TABLE_NAME = \''.$settings['db_prefix'].$table.'\';'
	);
	if ( !$check || mysqli_num_rows($check) !== 1 ) {
		echo 'Error: Table "'.$settings['db_prefix'].$table.'" not found after create_database().'.PHP_EOL;
		$failure = true;
	}
}
