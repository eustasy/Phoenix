<?php

////	parse_ipv4
// Attempts to parse an address string as IPv4, optionally with a `:port` suffix.
// Strips a leading `::ffff:` IPv4-mapped IPv6 prefix when present.
// Returns array('ip' => string, 'port' => int|false) on success, or false if the address is not valid IPv4.
function parse_ipv4(string $address): array|false {
	// IPv4-mapped IPv6 prefix is a literal 7-char string, not a character mask;
	// trim() would also chew leading/trailing 'f' and ':' from the address proper.
	$candidate = str_starts_with($address, '::ffff:') ? substr($address, 7) : $address;
	$port      = false;
	if ( strpos($candidate, ':') !== false ) {
		$parts     = explode(':', $candidate, 2);
		$candidate = $parts[0];
		// ctype_digit catches the bug in the original: explode() returns strings,
		// so the previous is_int() check would never match.
		if ( ctype_digit($parts[1]) ) {
			$port = intval($parts[1]);
		}
	}
	if ( !filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ) {
		return false;
	}
	return array('ip' => $candidate, 'port' => $port);
}
