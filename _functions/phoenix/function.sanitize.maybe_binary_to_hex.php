<?php

// Accepts a raw query-string value (not a $_GET value, which is already decoded).
// Calls urldecode() itself so binary bytes in %XX form are resolved exactly once.
//
// This is the project's primary SQL injection defense for info_hash and peer_id
// values, which are interpolated directly into single-quoted SQL strings. The
// ctype_xdigit() check is what makes that interpolation safe — it guarantees
// the returned value contains only [0-9a-f], which carries no SQL metacharacters.
// strtolower() below normalises pre-encoded uppercase hashes to the same lowercase
// form bin2hex() yields, so one torrent can't split into two swarms / miss the
// closed-tracker allowlist depending on how the client encoded the hash.
function maybe_binary_to_hex(string $binary) {
	$binary = urldecode($binary);
	// BEP 3: info_hash and peer_id are 20-byte SHA-1 values, URL-encoded as raw binary.
	// Some clients send them pre-encoded as 40-char hex strings; both forms are valid.
	if ( strlen($binary) === 20 ) {
		$binary = bin2hex($binary);
	}
	if ( strlen($binary) === 40 && ctype_xdigit($binary) ) {
		return strtolower($binary);
	}
	return false;
}