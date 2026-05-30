<?php

declare(strict_types=1);

////	view_error_bencode
// Renders a tracker error as a bencode failure reason dictionary (BEP 3).
// Returns the bencoded string but does NOT exit — caller is responsible for
// echoing and terminating the script.
function view_error_bencode(string $error): string {
	require_once __DIR__.'/../functions/bencode.encode.php';
	return bencode_encode(array('failure reason' => $error));
}
