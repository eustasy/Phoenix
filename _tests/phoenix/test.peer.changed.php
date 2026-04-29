<?php

require_once $settings['functions'].'function.peer.changed.php';

$current = array(
	'ipv4'   => '1.2.3.4',
	'ipv6'   => null,
	'portv4' => 80,
	'portv6' => 0,
	'state'  => 0,
);

// New peer (no previous row).
if ( !peer_changed($current, false) ) {
	echo 'Error: peer_changed should return true when there is no old row (false).'.PHP_EOL;
	$failure = true;
}
if ( !peer_changed($current, null) ) {
	echo 'Error: peer_changed should return true when old is null.'.PHP_EOL;
	$failure = true;
}

// Identical peer.
if ( peer_changed($current, $current) ) {
	echo 'Error: peer_changed should return false for identical peer.'.PHP_EOL;
	$failure = true;
}

// Each individual field change should register.
foreach ( array('ipv4', 'ipv6', 'portv4', 'portv6', 'state') as $field ) {
	$old = $current;
	$old[$field] = is_int($current[$field]) ? $current[$field] + 1 : '__changed__';
	if ( !peer_changed($current, $old) ) {
		echo 'Error: peer_changed should detect change in '.$field.'.'.PHP_EOL;
		$failure = true;
	}
}
