<?php

////	drop_table
// Drops a single prefixed table if it exists; echoes mysqli_error and returns false on failure.
function drop_table($connection, $settings, $table) {
	$result = mysqli_query($connection, 'DROP TABLE IF EXISTS `'.$settings['db_prefix'].$table.'`;');
	if ( !$result ) {
		echo mysqli_error($connection);
		return false;
	}
	return true;
}
