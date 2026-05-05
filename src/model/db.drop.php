<?php

declare(strict_types=1);

////	drop_table
// Drops a single prefixed table if it exists; echoes mysqli_error and returns false on failure.
function drop_table(mysqli $connection, array $settings, string $table): bool {
	$result = mysqli_query($connection, 'DROP TABLE IF EXISTS `'.$settings['db_prefix'].$table.'`;');
	if ( !$result ) {
		echo mysqli_error($connection);
		return false;
	}
	return true;
}
