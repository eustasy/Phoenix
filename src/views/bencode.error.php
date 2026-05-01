<?php

declare(strict_types=1);

////	view_error_bencode
// Renders a tracker error as a bencode failure reason dictionary (BEP 3).
// Returns the bencoded string but does NOT exit — caller is responsible for
// echoing and terminating the script.
function view_error_bencode(string $error): string {
	return 'd14:failure reason'.strlen($error).':'.$error.'e';
}
