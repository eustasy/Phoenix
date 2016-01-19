<?php

require_once $settings['functions'].'function.peer.completed.php';

$peer['info_hash'] = '__TEST_1__';

$result = peer_completed($connection, $settings, $peer);

$delete = 'DELETE FROM `'.$settings['db_prefix'].'torrents` WHERE `info_hash` LIKE \'__TEST_%\';';
mysqli_query($connection, $delete);

if ( !$result ) {
	echo 'Function did not execute successfully.'.PHP_EOL;
	exit(1);
}
