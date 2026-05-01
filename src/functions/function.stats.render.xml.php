<?php

////	stats_render_xml
// Render tracker statistics as XML.
// Outputs XML and terminates.

function stats_render_xml($stats, $settings) {
	header('Content-Type: text/xml');
	echo '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
		 '<tracker version="$Id: '.$settings['phoenix_version'].' $">'.
		 '<peers>'.$stats['peers'].'</peers>'.
		 '<seeders>'.$stats['seeders'].'</seeders>'.
		 '<leechers>'.$stats['leechers'].'</leechers>'.
		 '<torrents>'.$stats['torrents'].'</torrents>'.
		 '<downloads>'.$stats['downloads'].'</downloads>'.
		 '<traffic>'.$stats['traffic'].'</traffic></tracker>';
}
