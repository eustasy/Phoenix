<?php

declare(strict_types=1);

// Accepts a raw query-string value (not a $_GET value, which is already decoded).
// Calls urldecode() itself so binary bytes in %XX form are resolved exactly once.
function maybe_binary_to_hex(string $binary): string|false {
	$binary = urldecode($binary);
	if (
		strlen($binary) == 20 ||
		strlen($binary) == 40
	) {
		// BEP 3: info_hash and peer_id are 20-byte SHA-1 values, URL-encoded as raw binary.
		// Some clients send them pre-encoded as 40-char hex strings; both forms are valid.
		if ( strlen($binary) == 20 ) {
			$binary = bin2hex($binary);
		}
		// htmlentities as a final sanitization pass; safe hex chars (0-9a-f) pass through unchanged.
		$binary = htmlentities($binary, ENT_QUOTES, 'UTF-8');
		if ( strlen($binary) == 40 ) {
			return $binary;
		}
	}
	// Reject anything that isn't a valid 20-byte or 40-char hash.
	return false;
}
