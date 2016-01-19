<?php

require_once $settings['functions'].'function.tracker.allowed.php';

$result = tracker_allowed($connection, $settings);
if ( !empty($result) ) {
	echo 'Error: Empty query was not empty.'.PHP_EOL;
	$failure = true;
}

$insert = 'INSERT INTO `'.$settings['db_prefix'].'torrents` ( `info_hash` ) VALUES (\'__TEST_1__\'),  (\'__TEST_2__\'),  (\'__TEST_3__\');';
mysqli_query($connection, $insert);

$result = tracker_allowed($connection, $settings);
$count = count($result);
if ( $count != 3 ) {
	echo 'Error: Query for 3 items returned '.$count.PHP_EOL;
	$failure = true;
}

$delete = 'DELETE FROM `'.$settings['db_prefix'].'torrents` WHERE `info_hash` LIKE \'__TEST_%\';';
mysqli_query($connection, $delete);
