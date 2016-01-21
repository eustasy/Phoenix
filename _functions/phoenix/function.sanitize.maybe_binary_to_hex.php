<?php

function maybe_binary_to_hex($binary) {
	$binary = urldecode($binary);
	if (
		strlen($binary) == 20 ||
		strlen($binary) == 40
	) {
		// IF BINARY
		if ( strlen($binary) == 20 ) {
			$binary = bin2hex($binary);
		}
		// END IF BINARY
		$binary = htmlentities($binary, ENT_QUOTES, 'UTF-8');
		if ( strlen($binary) == 40 ) {
			return $binary;
		}
	}
	return false;
}
