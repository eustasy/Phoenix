<?php

////	mysqli_fetch_once
// Executes $sql and returns the first row as an associative array, or false if no rows match.
function mysqli_fetch_once(mysqli $connection, string $sql) {
	$result = mysqli_query($connection, $sql);
	if (
		$result &&
		mysqli_num_rows($result)
	) {
		return mysqli_fetch_assoc($result);
	}
	return false;
}
