<?php

require_once $settings['functions'].'function.parse.ipv4.php';
require_once $settings['functions'].'function.parse.ipv6.php';

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


////	Port
// Set the port if it's that easy.
if ( isset($_GET['port']) ) {
	$peer['port'] = intval($_GET['port']);
}

////	Find Possibles
// List all possible addresses.
$addresses = array();
if ( $settings['external_ip'] ) {
	if ( isset($_GET['ip']) ) {
		$addresses[] = $_GET['ip'];
	}
	if ( isset($_GET['ipv4']) ) {
		$addresses[] = $_GET['ipv4'];
	}
	if ( isset($_GET['ipv6']) ) {
		$addresses[] = $_GET['ipv6'];
	}
}
if ( isset($_SERVER['REMOTE_ADDR']) ) {
	$addresses[] = $_SERVER['REMOTE_ADDR'];
}
// If we're honoring X_FORWARDED_FOR, we check and use that first if it's present.
if (
	isset($_SERVER['HTTP_CLIENT_IP']) &&
	$settings['honor_xff']
) {
	$addresses[] = $_SERVER['HTTP_CLIENT_IP'];
}
if (
	isset($_SERVER['HTTP_X_FORWARDED_FOR']) &&
	$settings['honor_xff']
) {
	$addresses[] = $_SERVER['HTTP_X_FORWARDED_FOR'];
}
// Error if we can't find any addresses.
if ( !count($addresses) ) {
	tracker_error('Unable to obtain client IP');
}
	
// Reverse so later-added candidates are checked first (including X-Forwarded-For/Client-IP when honor_xff is enabled).
$addresses = array_reverse($addresses);

////	Find Definites
// Find the highest possible rank for IPv4 and IPv6, plus their associated ports.
foreach ( $addresses as $address ) {
	if ( !$peer['ipv4'] && ($ipv4 = parse_ipv4($address)) !== false ) {
		$peer['ipv4'] = $ipv4['ip'];
		if ( !$peer['portv4'] && $ipv4['port'] !== false ) {
			$peer['portv4'] = $ipv4['port'];
		}
	}
	if ( !$peer['ipv6'] && ($ipv6 = parse_ipv6($address)) !== false ) {
		$peer['ipv6'] = $ipv6['ip'];
		if ( !$peer['portv6'] && $ipv6['port'] !== false ) {
			$peer['portv6'] = $ipv6['port'];
		}
	}
}

if ( $peer['port'] && !$peer['portv4'] ) {
	$peer['portv4'] = $peer['port'];
}
if ( $peer['port'] && !$peer['portv6'] ) {
	$peer['portv6'] = $peer['port'];
}
