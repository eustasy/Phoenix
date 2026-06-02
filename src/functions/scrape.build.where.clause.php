<?php

declare(strict_types=1);

////	scrape_build_where_clause
// Build WHERE clause for scraping multiple info_hashes.
// Returns SQL WHERE clause string, or '' when there are no hashes (so callers
// don't accidentally append a bare 'WHERE ' to their query).

/** @param array<int|string, string> $info_hashes */
function scrape_build_where_clause(array $info_hashes): string
{
    if (empty($info_hashes)) {
        return '';
    }
    $where = 'WHERE ';
    foreach ($info_hashes as $count => $info_hash) {
        if ($count > 0) {
            $where .= ' OR';
        }
        $where .= ' `p`.`info_hash`=\''.$info_hash.'\'';
    }

    return $where;
}
