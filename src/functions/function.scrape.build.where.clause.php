<?php

////	scrape_build_where_clause
// Build WHERE clause for scraping multiple info_hashes.
// Returns SQL WHERE clause string.

function scrape_build_where_clause($info_hashes) {
	$where = 'WHERE ';
	foreach ( $info_hashes as $count => $info_hash ) {
		if ( $count > 0 ) {
			$where .= ' OR';
		}
		$where .= ' `p`.`info_hash`=\''.$info_hash.'\'';
	}
	return $where;
}
