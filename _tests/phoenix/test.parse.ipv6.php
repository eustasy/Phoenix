<?php

require_once $settings['functions'].'function.parse.ipv6.php';

// Plain IPv6
$r = parse_ipv6('dead:beef::1234');
if ( $r === false || $r['ip'] !== 'dead:beef::1234' || $r['port'] !== false ) {
	echo 'Error: parse_ipv6 plain IPv6 failed.'.PHP_EOL;
	$failure = true;
}

// Bracketed with port
$r = parse_ipv6('[dead:beef::1234]:12345');
if ( $r === false || $r['ip'] !== 'dead:beef::1234' || $r['port'] !== 12345 ) {
	echo 'Error: parse_ipv6 bracketed with port failed.'.PHP_EOL;
	$failure = true;
}

// Bracketed without port
$r = parse_ipv6('[dead:beef::1234]');
if ( $r === false || $r['ip'] !== 'dead:beef::1234' || $r['port'] !== false ) {
	echo 'Error: parse_ipv6 bracketed without port failed.'.PHP_EOL;
	$failure = true;
}

// Out-of-range port is ignored; the IPv6 address is still accepted with no port.
$r = parse_ipv6('[dead:beef::1234]:99999');
if ( $r === false || $r['ip'] !== 'dead:beef::1234' || $r['port'] !== false ) {
	echo 'Error: parse_ipv6 should accept the IPv6 and ignore a port above 65535.'.PHP_EOL;
	$failure = true;
}

// IPv4 input rejected
if ( parse_ipv6('101.45.75.219') !== false ) {
	echo 'Error: parse_ipv6 should reject IPv4.'.PHP_EOL;
	$failure = true;
}

// Garbage input
if ( parse_ipv6('not an address') !== false ) {
	echo 'Error: parse_ipv6 should reject garbage.'.PHP_EOL;
	$failure = true;
}

// Empty input
if ( parse_ipv6('') !== false ) {
	echo 'Error: parse_ipv6 should reject empty string.'.PHP_EOL;
	$failure = true;
}
