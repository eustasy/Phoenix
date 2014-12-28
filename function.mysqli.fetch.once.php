<?php

function mysqli_fetch_once($query) {

	global $connection, $settings;

	$result = mysqli_query($connection, $query);
	if (
		$result &&
		mysqli_num_rows($result)
	) {
		return mysqli_fetch_assoc($result);
	} else {
		return false;
	}

}