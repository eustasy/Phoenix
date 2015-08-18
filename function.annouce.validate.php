<?php

function validate_ipv6() {
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
			tracker_error('Did not get port and was not specified via ipv6=');
		}
	} else {
		// Nope. This just got considerably more annoying.
		// Our first char must be '['
		if ( $_GET['ipv6'][0] ==! '[' ) {
			tracker_error('Invalid IPv6 address');
		}

		$end_mark = strpos($_GET['ipv6'], ']')-1;
		if ( !$end_mark ) {
			tracker_error('Invalid IPv6 address');
		}

		$v6_addr = substr($_GET['ipv6'], 1, $end_mark);
		if (! filter_var($v6_addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ) {
			tracker_error('Invalid IPv6 address');
		}

		// Got the address, fish the port if its there
		if ( (strlen($v6_adr)+2) == strlen($_GET['ipv6']) ) {
			// Port wasn't included, copy it from port= if its there ...
			if ( isset($_GET['port']) && is_numeric($_GET['port']) ) {
				$_GET['portv6'] = $_GET['port'];
			} else {
				tracker_error('Did not get port, and was not specified via ipv6, or was not a valid integer');
			}
			$_GET['ipv6'] = $v6_addr;
		}

		// Try to get the port from the end of the string
		$_GET['portv6'] = substr($_GET['ipv6'], strlen($v6_addr)+3);
		$_GET['ipv6'] = $v6_addr;
		if (!is_numeric($_GET['portv6'])) {
			tracker_error('Invalid Port at end of v6 string');
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
