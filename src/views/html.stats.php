<?php

////	view_stats_html
// Render tracker statistics as HTML.
// Returns HTML string. Caller is responsible for setting Content-Type header.

function view_stats_html($stats, $settings): string {
	return '<!DocType html><html><head><meta charset="UTF-8">'.
		 '<title>Phoenix: $Id: '.$settings['phoenix_version'].' $</title>'.
		 '<body><pre>'.number_format($stats['peers']).
		 ' peers ('.number_format($stats['seeders']).' seeders + '.number_format($stats['leechers']).
		 ' leechers) in '.number_format($stats['torrents']).' torrents and'.
		 ' '.number_format($stats['downloads']).' downloads completed,'.
		 ' '.number_format($stats['traffic']).' bytes served.</pre></body></html>';
}
