<?php

declare(strict_types=1);

////	index_render_html
// Renders a normalised $index array as the HTML form of a torrent index response.
// Caller is responsible for emitting the Content-Type header.
function index_render_html(array $index): string {
	$html = '<!DocType html><html><head><meta charset="UTF-8"><title>Torrent Index</title></head><body><ul>';
	foreach ( $index as $torrent ) {
		$html .= '<li>'.htmlspecialchars($torrent['name']).
			' &mdash; '.$torrent['seeders'].' seeders,'.
			' '.$torrent['leechers'].' leechers,'.
			' '.$torrent['downloads'].' downloads</li>';
	}
	return $html.'</ul></body></html>';
}
