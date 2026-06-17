<?php

declare(strict_types=1);

////	view_error_bencode
// Renders a tracker error as a bencode failure reason dictionary (BEP 3).
// When $retry_in is given it adds BEP 31's 'retry in' key — an integer number
// of seconds the client should wait, or the string "never" for a permanent
// rejection. Returns the bencoded string but does NOT exit — caller is
// responsible for echoing and terminating the script.
function view_error_bencode(string $error, int|string|null $retry_in = null): string
{
    require_once __DIR__.'/../functions/bencode.encode.php';

    $response = ['failure reason' => $error];
    if ($retry_in !== null) {
        $response['retry in'] = $retry_in;
    }

    return bencode_encode($response);
}
