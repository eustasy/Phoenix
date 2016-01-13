<?php

////	$_GET['info_hash']
// TODO: Handle Multiples
// IF 20 Character Binay, convert to 40 Character Hex
// OR 40 Character Hex
// THEN Sanatize
$peer['info_hash'] = false;
if ( isset($_GET['info_hash']) ) {
	// IF info_hash BINARY
	if ( strlen($_GET['info_hash']) == 20 ) {
		$peer['info_hash'] = bin2hex($_GET['info_hash']);
	// END IF info_hash BINARY
	// IF info_hash HEX
	} else if ( strlen($_GET['info_hash']) == 40 ) {
		$peer['info_hash'] = $_GET['info_hash'];
	}
	// END IF info_hash HEX
}
if ( !empty($peer['info_hash']) ) {
	$peer['info_hash'] = htmlentities($peer['info_hash'], ENT_QUOTES, 'UTF-8');
}

////	$_GET['peer_id']
// IF 20 Character Binay, convert to 40 Character Hex
// OR 40 Character Hex
// THEN Sanatize
$peer['peer_id'] = false;
if ( isset($_GET['peer_id']) ) {
	// IF peer_id BINARY
	if ( strlen($_GET['peer_id']) == 20 ) {
		$peer['peer_id'] = bin2hex($_GET['peer_id']);
	// END IF peer_id BINARY
	// IF peer_id HEX
	} else if ( strlen($_GET['peer_id']) == 40 ) {
		$peer['peer_id'] = $_GET['peer_id'];
	}
	// END IF peer_id HEX
}
if ( !empty($peer['peer_id']) ) {
	$peer['peer_id'] = htmlentities($peer['peer_id'], ENT_QUOTES, 'UTF-8');
}
