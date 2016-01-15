<?php

function mysqli_fetch_once($connection, $sql) {
	$result = mysqli_query($connection, $sql);
	if (
		$result &&
		mysqli_num_rows($result)
	) {
		return mysqli_fetch_assoc($result);
	} else {
		return false;
	}
}
