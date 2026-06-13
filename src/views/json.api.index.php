<?php

declare(strict_types=1);

////	view_api_index_json

//	Returns the API discovery index as a JSON-encoded string: the running
//	Phoenix version, under a top-level 'phoenix' object (room to grow without
//	breaking the shape).
//	Input: $settings array (needs phoenix_version).
//	Output: JSON string.

/** @param PhoenixSettings $settings */
function view_api_index_json(array $settings): string
{
    return json_encode([
        'phoenix' => [
            'version' => $settings['phoenix_version'],
        ],
    ]) ?: '';
}
