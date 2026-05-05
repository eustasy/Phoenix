<?php

declare(strict_types=1);

////	torrents_select_allowed
// Returns the list of permitted info_hashes for closed-tracker mode.
// Returns an empty array (not an error) when no torrents are registered.
function torrents_select_allowed(mysqli $connection, array $settings): array {
	require_once $settings['model'].'db.fetch.column.php';
	$sql = 'SELECT `info_hash` FROM `'.$settings['db_prefix'].'torrents`;';
	$allowed_torrents = db_fetch_column($connection, $sql);
	if ( !$allowed_torrents ) {
		return array();
	}
	return $allowed_torrents;
}
