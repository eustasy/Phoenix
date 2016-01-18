<?php

require_once $settings['functions'].'function.mysqli.drop.table.php';

$create = 'CREATE TABLE `__TEST__` ( `id` int(10) );';
$result = mysqli_query($connection, $create);

$result = drop_table($connection, $settings, '__TEST__');
if ( !$result ) {
	echo 'Error: Empty query was not empty.'.PHP_EOL;
	exit(1);
}
