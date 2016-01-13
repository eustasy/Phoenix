<?php

function maybe_binary_to_hex($binary) {
	if (
		strlen($binary) == 20 ||
		strlen($binary) == 40
	) {
		// IF BINARY
		if ( strlen($binary) == 20 ) {
			$binary = bin2hex($binary);
		}
		// END IF BINARY
		return htmlentities($binary, ENT_QUOTES, 'UTF-8');
	} else {
		return false;
	}
}
