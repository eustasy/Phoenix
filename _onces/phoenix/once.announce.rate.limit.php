<?php

require_once $settings['functions'].'function.mysqli.fetch.once.php';

$ip_parts = array();
if ( $peer['ipv4'] ) $ip_parts[] = '`ipv4`=\''.$peer['ipv4'].'\'';
if ( $peer['ipv6'] ) $ip_parts[] = '`ipv6`=\''.$peer['ipv6'].'\'';

if ( !empty($ip_parts) ) {
	$ip_threshold = $time - intval($settings['min_interval'] / 5);

	$rate = mysqli_fetch_once($connection,
		'SELECT COUNT(*) AS `count` FROM `'.$settings['db_prefix'].'peers` '.
		'WHERE `info_hash`=\''.$peer['info_hash'].'\' '.
		'AND ('.implode(' OR ', $ip_parts).') '.
		'AND `peer_id`!=\''.$peer['peer_id'].'\' '.
		'AND `updated`>'.$ip_threshold.';'
	);

	if ( $rate && intval($rate['count']) > 0 ) {
		tracker_error('Announce rate limit exceeded.');
	}
}
