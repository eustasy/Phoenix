<?php

require_once $settings['functions'].'function.parse.ipv4.php';

// Plain IPv4
$r = parse_ipv4('101.45.75.219');
if ( $r === false || $r['ip'] !== '101.45.75.219' || $r['port'] !== false ) {
	echo 'Error: parse_ipv4 plain IPv4 failed.'.PHP_EOL;
	$failure = true;
}

// IPv4 with port
$r = parse_ipv4('101.45.75.219:12345');
if ( $r === false || $r['ip'] !== '101.45.75.219' || $r['port'] !== 12345 ) {
	echo 'Error: parse_ipv4 with port failed.'.PHP_EOL;
	$failure = true;
}

// Non-numeric port is ignored; the IPv4 address is still accepted with no port.
$r = parse_ipv4('101.45.75.219:abc');
if ( $r === false || $r['ip'] !== '101.45.75.219' || $r['port'] !== false ) {
	echo 'Error: parse_ipv4 should accept the IPv4 and ignore a non-numeric port.'.PHP_EOL;
	$failure = true;
}

// IPv6 input rejected
if ( parse_ipv4('dead:beef::1234') !== false ) {
	echo 'Error: parse_ipv4 should reject IPv6.'.PHP_EOL;
	$failure = true;
}

// Garbage input
if ( parse_ipv4('not an address') !== false ) {
	echo 'Error: parse_ipv4 should reject garbage.'.PHP_EOL;
	$failure = true;
}

// Empty input
if ( parse_ipv4('') !== false ) {
	echo 'Error: parse_ipv4 should reject empty string.'.PHP_EOL;
	$failure = true;
}
