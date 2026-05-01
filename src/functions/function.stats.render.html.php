<?php

////	stats_render_html
// Render tracker statistics as HTML.
// Outputs HTML and terminates.

function stats_render_html($stats, $settings) {
	echo '<!DocType html><html><head><meta charset="UTF-8">'.
		 '<title>Phoenix: $Id: '.$settings['phoenix_version'].' $</title>'.
		 '<body><pre>'.number_format($stats['peers']).
		 ' peers ('.number_format($stats['seeders']).' seeders + '.number_format($stats['leechers']).
		 ' leechers) in '.number_format($stats['torrents']).' torrents and'.
		 ' '.number_format($stats['downloads']).' downloads completed,'.
		 ' '.number_format($stats['traffic']).' bytes served.</pre></body></html>';
}
