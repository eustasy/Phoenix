<?php

function validate_ipv6() {
	$stderr = fopen('php://stderr', 'w');

	// If we get an IPv6 parameter, use that, else, use client's address
	// The IPv6 address can either be in the form of:
	// dead:beef::1234 - i.e., raw address, in which case we use port=
	// -or-
	// [dead:beef::1234]:12345 - i.e., enbedded port. Unfortunately
	// PHP has no functions for handling IPv6 addresses so we'll
	// have to roll our own

	// Check the easy case first ..
	if ( filter_var($_GET['ipv6'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ) {
		// Sweet, copy the port to port6, and we're done
		if ( isset($_GET['port']) && is_numeric($_GET['port']) ) {
			$_GET['portv6'] = $_GET['port'];
		} else {
			fwrite($stderr, "Bad port, bailing out\n");
			tracker_error('Did not get port and was not specified via ipv6=');
		}
	} else {
		// Nope. This just got considerably more annoying.
		// Our first char must be '['
		// FIXME: finish this
		if ( $client_ip[0] ==! '[' ) {
			tracker_error('Invalid IPv6 address');
		}
	}
}

function validate_ipv4() {
	$_GET['ipv4'] = trim($_GET['ipv4'],'::ffff:');
	if ( strpos($_GET['ipv4'], ':') !== false ) {
		$_GET['ipv4'] = explode(':', $_GET['ipv4']);
		$_GET['portv4'] = $_GET['ipv4'][1];
		$_GET['ipv4'] = $_GET['ipv4'][0];
	} else {
		$_GET['portv4'] = $_GET['port'];
	}
}

?>
