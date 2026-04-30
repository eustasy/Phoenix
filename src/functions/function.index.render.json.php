<?php

declare(strict_types=1);

////	index_render_json
// Renders a normalised $index array as the JSON form of a torrent index response.
// Caller is responsible for emitting the Content-Type header.
function index_render_json(array $index): string {
	return json_encode($index);
}
