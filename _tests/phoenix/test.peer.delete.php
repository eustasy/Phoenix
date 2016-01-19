<?php

require_once $settings['functions'].'function.peer.delete.php';
require_once $settings['functions'].'function.mysqli.fetch.once.php';

$insert = 'INSERT INTO `'.$settings['db_prefix'].'peers` ( `info_hash`, `peer_id` ) VALUES ( \'__TEST_1__\', \'__TEST_1__\' );';
mysqli_query($connection, $insert);

$peer['info_hash'] = '__TEST_1__';
$peer['peer_id'] = '__TEST_1__';

peer_delete($connection, $settings, $peer);

$select = 'SELECT * FROM `'.$settings['db_prefix'].'peers` WHERE `info_hash` = \'__TEST_1__\' AND `peer_id` = \'__TEST_1__\';';
$result = mysqli_fetch_once($connection, $select);

$delete = 'DELETE FROM `'.$settings['db_prefix'].'peers` WHERE `info_hash` LIKE \'__TEST_%\';';
mysqli_query($connection, $delete);

if ( $result ) {
	echo 'Peer does not appear to have been deleted.'.PHP_EOL;
	exit(1);
}
