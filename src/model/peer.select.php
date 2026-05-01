<?php

declare(strict_types=1);

////	peer_select
// SELECT single peer by info_hash + peer_id.
// Returns the peer row as an associative array, null if not found, or false on query error.

function peer_select(mysqli $connection, array $settings, array $peer): array|false|null {
	require_once $settings['model'].'db.fetch.once.php';
	return mysqli_fetch_once(
		$connection,
		'SELECT * FROM `'.$settings['db_prefix'].'peers` '.
		'WHERE `info_hash`=\''.$peer['info_hash'].'\' AND `peer_id`=\''.$peer['peer_id'].'\';'
	);
}
