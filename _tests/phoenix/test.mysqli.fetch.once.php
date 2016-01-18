<?php

$failure = false;
require_once $settings['functions'].'function.mysqli.fetch.once.php';
$sql = 'SELECT `info_hash` FROM `'.$settings['db_prefix'].'torrents`;';

$result = mysqli_fetch_once($connection, $sql);
if ( $result ) {
	echo 'Error: Query returned items for an expected empty result.'.$count.PHP_EOL;
	$failure = true;
}

$insert = 'INSERT INTO `'.$settings['db_prefix'].'torrents` ( `info_hash` ) VALUES (\'__TEST_1__\'),  (\'__TEST_2__\'),  (\'__TEST_3__\');';
$result = mysqli_query($connection, $insert);

$result = mysqli_fetch_once($connection, $sql);
$count = count($result);
if ( $count != 1 ) {
	echo 'Error: Query returned '.$count.' items.'.PHP_EOL;
	$failure = true;
}

$insert = 'DELETE FROM `'.$settings['db_prefix'].'torrents` WHERE `info_hash` LIKE \'__TEST_%\';';
$result = mysqli_query($connection, $insert);
if ( $failure ) {
	exit(1);
}
