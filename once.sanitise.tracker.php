<?php

require_once __DIR__.'/function.sanitise.maybe_binary_to_hex.php';

////	$_GET['info_hash']
// TODO: Handle Multiples
// IF 20 Character Binay, convert to 40 Character Hex
// OR 40 Character Hex
// THEN Sanatize
$peer['info_hash'] = false;
if ( isset($_GET['info_hash']) ) {
	$peer['info_hash'] = maybe_binary_to_hex($_GET['info_hash']);
}

////	$_GET['peer_id']
// IF 20 Character Binay, convert to 40 Character Hex
// OR 40 Character Hex
// THEN Sanatize
$peer['peer_id'] = false;
if ( isset($_GET['peer_id']) ) {
	$peer['peer_id'] = maybe_binary_to_hex($_GET['peer_id']);
}
