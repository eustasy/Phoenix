<?php

declare(strict_types=1);

////	scrape_initialize_results
// Initialize scrape results array with zero values for all requested info_hashes.
// BEP 15 requires responses for all requested hashes, even if unknown.
// Returns array indexed by info_hash with zeroed stats.

function scrape_initialize_results(array $info_hashes): array {
	$scrape = array();
	foreach ( $info_hashes as $info_hash ) {
		$scrape[$info_hash]['info_hash'] = $info_hash;
		$scrape[$info_hash]['seeders']   = 0;
		$scrape[$info_hash]['leechers']  = 0;
		$scrape[$info_hash]['downloads'] = 0;
		$scrape[$info_hash]['peers']     = 0;
		$scrape[$info_hash]['size']      = 0;
		$scrape[$info_hash]['traffic']   = 0;
	}
	return $scrape;
}
