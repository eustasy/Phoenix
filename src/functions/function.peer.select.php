<?php

declare(strict_types=1);

////	peer_select
// Fetches the existing peers row for a given info_hash + peer_id pair,
// or false when no row exists.
function peer_select(mysqli $connection, array $settings, array $peer): array|false|null {
	require_once $settings['functions'].'function.mysqli.fetch.once.php';
	return mysqli_fetch_once(
		$connection,
		'SELECT * FROM `'.$settings['db_prefix'].'peers` '.
		'WHERE `info_hash`=\''.$peer['info_hash'].'\' AND `peer_id`=\''.$peer['peer_id'].'\';'
	);
}
