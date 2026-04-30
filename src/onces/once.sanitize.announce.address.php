<?php

declare(strict_types=1);

require_once $settings['functions'].'function.parse.ipv4.php';
require_once $settings['functions'].'function.parse.ipv6.php';
require_once $settings['functions'].'function.peer.address.candidates.php';
require_once $settings['functions'].'function.peer.resolve.addresses.php';

// Examples of address forms accepted downstream:
// 101.45.75.219:12345
// 101.45.75.219 &port= 12345
// ::FFFF:101.45.75.219 &port= 12345
// dead:beef::1234 &port= 12345
// [dead:beef::1234] &port= 12345
// [dead:beef::1234]:12345

$peer['ipv4']   = false;
$peer['ipv6']   = false;
$peer['port']   = false;
$peer['portv4'] = false;
$peer['portv6'] = false;

if ( isset($_GET['port']) ) {
	$peer['port'] = intval($_GET['port']);
}

$candidates = peer_address_candidates($settings, $_GET, $_SERVER);
if ( !count($candidates) ) {
	tracker_error('Unable to obtain client IP');
}

$resolved = peer_resolve_addresses($candidates);
$peer['ipv4']   = $resolved['ipv4'];
$peer['ipv6']   = $resolved['ipv6'];
$peer['portv4'] = $resolved['portv4'];
$peer['portv6'] = $resolved['portv6'];

// Fall back to ?port= when the resolved address didn't supply its own port.
if ( $peer['port'] && !$peer['portv4'] ) {
	$peer['portv4'] = $peer['port'];
}
if ( $peer['port'] && !$peer['portv6'] ) {
	$peer['portv6'] = $peer['port'];
}
