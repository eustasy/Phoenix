<?php

declare(strict_types=1);

////	view_error_json
// Renders a tracker error as JSON, mirroring BEP 31's 'retry in' (as retry_in)
// when $retry_in is given — seconds, or "never". Returns the JSON string but
// does NOT exit — caller is responsible for echoing and terminating the script.
function view_error_json(string $error, int|string|null $retry_in = null): string
{
    $response = ['error' => $error];
    if ($retry_in !== null) {
        $response['retry_in'] = $retry_in;
    }

    return json_encode($response) ?: '';
}
