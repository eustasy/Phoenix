<?php

////	parse_ipv6
// Attempts to parse an address string as IPv6, optionally bracketed with `[ip]:port`.
// Returns array('ip' => string, 'port' => int|false) on success, or false if the address is not valid IPv6.
function parse_ipv6($address) {
	$candidate = $address;
	$port      = false;
	if ( strpos($candidate, ']:') !== false ) {
		$parts     = explode(']:', $candidate, 2);
		$candidate = $parts[0];
		if ( ctype_digit($parts[1]) ) {
			$port = intval($parts[1]);
		}
	}
	if ( strpos($candidate, '[') !== false ) {
		$candidate = trim($candidate, '[]');
	}
	if ( !filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ) {
		return false;
	}
	return array('ip' => $candidate, 'port' => $port);
}
