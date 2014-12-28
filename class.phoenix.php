<?php

// Phoenix Core
class phoenix {

	// tracker statistics
	public static function allowed_torrents() {
		$response = array();
		$stats = self::$api->array_build(
			'SELECT `info_hash` FROM `'.$settings['db_prefix'].'torrents`',
			$response
		) OR tracker_error('Failed to retrieve allowed torrents.');
		return $response;
	}


}