<?php

$failure = false;
require_once $settings['functions'].'function.mysqli.array.build.php';

$select = 'SELECT `info_hash` FROM `'.$settings['db_prefix'].'torrents`;';
$result = mysqli_array_build($connection, $select);
if ( !empty($result) ) {
	echo 'Error: Empty query was not empty.'.PHP_EOL;
	$failure = true;
}

$insert = 'INSERT INTO `'.$settings['db_prefix'].'torrents` ( `info_hash` ) VALUES (\'__TEST_1__\'),  (\'__TEST_2__\'),  (\'__TEST_3__\');';
$result = mysqli_query($connection, $insert);

$result = mysqli_array_build($connection, $select);
$count = count($result);
if ( $count != 3 ) {
	echo 'Error: Query for 3 items returned '.$count.PHP_EOL;
	$failure = true;
}

if ( $failure ) {
	exit(1);
}
