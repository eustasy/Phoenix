<?php

declare(strict_types=1);

////	parse_ipv4
// Attempts to parse an address string as IPv4, optionally with a `:port` suffix.
// Strips a leading `::ffff:` IPv4-mapped IPv6 prefix when present.
// Returns array('ip' => string, 'port' => int|false) on success, or false if the address is not valid IPv4.
function parse_ipv4(string $address): array|false {
	$candidate = trim($address, '::ffff:');
	$port      = false;
	if ( strpos($candidate, ':') !== false ) {
		$parts     = explode(':', $candidate, 2);
		// A colon followed by non-numeric characters is malformed.
		if ( !ctype_digit($parts[1]) ) {
			return false;
		}
		$candidate = $parts[0];
		$port      = intval($parts[1]);
	}
	if ( !filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ) {
		return false;
	}
	return array('ip' => $candidate, 'port' => $port);
}
