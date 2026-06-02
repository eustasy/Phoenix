<?php

declare(strict_types=1);

////	view_error_json
// Renders a tracker error as JSON. Returns the JSON string but does NOT exit —
// caller is responsible for echoing and terminating the script.
function view_error_json(string $error): string
{
    return json_encode(['error' => $error]) ?: '';
}
