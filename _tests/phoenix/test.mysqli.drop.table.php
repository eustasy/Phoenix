<?php

require_once $settings['functions'].'function.mysqli.drop.table.php';

$create = 'CREATE TABLE `'.$settings['db_prefix'].'__TEST__` ( `id` int(10) );';
mysqli_query($connection, $create);

$result = drop_table($connection, $settings, '__TEST__');
if ( !$result ) {
	echo 'Error: Empty query was not empty.'.PHP_EOL;
	$failure = true;
}
