<?php

function mysqli_array_build($sql) {

	global $connection, $settings;

	require_once __DIR__.'/once.db.connect.php';

	$result = mysqli_query($connection, $sql);
	if ( !$result ) {
		return false;
	} else {
		while ( $thing = mysqli_fetch_array($result) ) {
			$response[] = $thing[0];
		}
		return $response;
	}

}