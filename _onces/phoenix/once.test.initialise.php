<?php

$test_db = mysqli_connect('127.0.0.1', 'root', '', 'phoenix');
if ( !$test_db ) {
	exit('Failed to connect to database for testing.');
}
$query = 'CREATE USER phoenix@localhost IDENTIFIED BY \'Password1\';';
$query .= 'GRANT ALL PRIVILEGES ON *.* TO phoenix@localhost;';
$query .= 'FLUSH PRIVILEGES;';
mysqli_multi_query($test_db, $query);
