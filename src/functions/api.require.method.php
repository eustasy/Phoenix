<?php

declare(strict_types=1);

////	api_require_method
// Enforce the HTTP method an API endpoint accepts: reads are GET, mutations are
// POST (so a torrent can't be changed by a cross-site image/link, and keys
// aren't pushed into query strings). Calls tracker_error('Method not allowed.')
// — which exits — when the request method doesn't match. The entry point
// pre-sets the JSON flag so the error serialises as JSON unless ?xml is set.

function api_require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        tracker_error('Method not allowed.');
    }
}
