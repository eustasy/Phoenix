<?php

require_once $settings['functions'].'function.sanitize.maybe_binary_to_hex.php';

////	$_GET['info_hash']
// IF 20 Character Binay, convert to 40 Character Hex
// OR 40 Character Hex
// THEN Sanatize
$peer['info_hash'] = false;
$peer['info_hashes'] = array();
$peer['peer_id'] = false;
$params = explode('&', $_SERVER['QUERY_STRING']);
foreach ( $params as $param ) {
	$param = explode('=', $param, 2);
	if ( $param[0] == 'info_hash' ) {
		$peer['info_hashes'][] = maybe_binary_to_hex($param[1]);
	} else if ( $param[0] == 'peer_id' && !$peer['peer_id'] ) {
		$peer['peer_id'] = maybe_binary_to_hex($param[1]);
	}
}
if ( !empty($peer['info_hashes'][0]) ) {
	$peer['info_hash'] = $peer['info_hashes'][0];
}
