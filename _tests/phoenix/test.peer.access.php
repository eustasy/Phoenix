<?php

require_once $settings['functions'].'function.peer.access.php';
require_once $settings['functions'].'function.mysqli.fetch.once.php';

$insert = 'INSERT INTO `'.$settings['db_prefix'].'peers` ( `info_hash`, `peer_id`, `left` ) VALUES (\'__TEST_1__\', \'__TEST_1__\', \'3\');';
$result = mysqli_query($connection, $insert);

$peer['info_hash'] = '__TEST_1__';
$peer['peer_id'] = '__TEST_1__';
$peer['left'] = 2;

peer_access($connection, $settings, $time, $peer);

$select = 'SELECT `left` FROM '.$settings['db_prefix'].'peers` WHERE `info_hash` = \'__TEST_1__\' AND `peer_id` = \'__TEST_1__\';';
$result = mysqli_fetch_once($connection, $select);
if ( $result != 2 ) {
	echo 'Result did not equal 2.'.PHP_EOL;
	exit(1);
}
