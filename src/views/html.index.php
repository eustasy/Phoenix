<?php

declare(strict_types=1);

////	view_index_html
// Renders a normalized $index array as HTML torrent index.
// Returns HTML string. Caller is responsible for setting Content-Type header.

function view_index_html(array $index): string {
	$html = '<!DocType html><html><head><meta charset="UTF-8"><title>Torrent Index</title></head><body><ul>';
	foreach ( $index as $torrent ) {
		$html .= '<li>'.htmlspecialchars($torrent['name']).
			' &mdash; '.$torrent['seeders'].' seeders,'.
			' '.$torrent['leechers'].' leechers,'.
			' '.$torrent['downloads'].' downloads</li>';
	}
	return $html.'</ul></body></html>';
}
