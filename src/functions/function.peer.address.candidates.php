<?php

declare(strict_types=1);

////	peer_address_candidates
// Builds an ordered list of candidate IP addresses from $settings, $_GET, and
// $_SERVER. The list is reversed before return so trusted-last sources
// (X-Forwarded-For / Client-IP, when honor_xff is enabled) take precedence
// over REMOTE_ADDR, which in turn takes precedence over client-supplied
// values from external_ip.
function peer_address_candidates(array $settings, array $get, array $server): array {
	$addresses = array();
	if ( $settings['external_ip'] ) {
		if ( isset($get['ip']) )   { $addresses[] = $get['ip']; }
		if ( isset($get['ipv4']) ) { $addresses[] = $get['ipv4']; }
		if ( isset($get['ipv6']) ) { $addresses[] = $get['ipv6']; }
	}
	if ( isset($server['REMOTE_ADDR']) ) {
		$addresses[] = $server['REMOTE_ADDR'];
	}
	if ( $settings['honor_xff'] ) {
		if ( isset($server['HTTP_CLIENT_IP']) )       { $addresses[] = $server['HTTP_CLIENT_IP']; }
		if ( isset($server['HTTP_X_FORWARDED_FOR']) ) { $addresses[] = $server['HTTP_X_FORWARDED_FOR']; }
	}
	return array_reverse($addresses);
}
