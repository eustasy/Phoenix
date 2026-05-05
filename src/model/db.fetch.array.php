<?php

declare(strict_types=1);

////	mysqli_array_build
// Executes $sql and returns the first column of every result row as a flat indexed array.
function mysqli_array_build(mysqli $connection, string $sql): array {
	$result = mysqli_query($connection, $sql);
	if ( !$result ) {
		tracker_error('Failed to build array.');
	}
	$response = array();
	while ( $thing = mysqli_fetch_array($result) ) {
		$response[] = $thing[0];
	}
	return $response;
}
