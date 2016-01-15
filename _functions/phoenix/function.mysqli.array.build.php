<?php

function mysqli_array_build($connection, $sql) {
	$result = mysqli_query($connection, $sql);
	if ( !$result ) {
		tracker_error('Failed to build array.');
	} else {
		$response = array();
		while ( $thing = mysqli_fetch_array($result) ) {
			$response[] = $thing[0];
		}
		return $response;
	}
}
