<?php

declare(strict_types=1);

////	peer_resolve_addresses
// Iterates the given address candidates, returning the first valid IPv4 and
// the first valid IPv6 found, plus their associated ports (or false when no
// port could be parsed).

function peer_resolve_addresses(array $addresses): array {
	require_once __DIR__.'/parse.ipv4.php';
	require_once __DIR__.'/parse.ipv6.php';

	$result = array(
		'ipv4'   => false,
		'ipv6'   => false,
		'portv4' => false,
		'portv6' => false,
	);
	foreach ( $addresses as $address ) {
		if ( !$result['ipv4'] && ($ipv4 = parse_ipv4($address)) !== false ) {
			$result['ipv4']   = $ipv4['ip'];
			$result['portv4'] = $ipv4['port'];
		}
		if ( !$result['ipv6'] && ($ipv6 = parse_ipv6($address)) !== false ) {
			$result['ipv6']   = $ipv6['ip'];
			$result['portv6'] = $ipv6['port'];
		}
	}
	return $result;
}
