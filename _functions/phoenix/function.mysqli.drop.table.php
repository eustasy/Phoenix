<?php

function drop_table($connection, $settings, $table) {
	$result = mysqli_query($connection, 'DROP TABLE IF EXISTS `'.$settings['db_prefix'].$table.'`;');
	if ( !$result ) {
		echo mysqli_error($connection);
		return false;
	}
	return true;
}
