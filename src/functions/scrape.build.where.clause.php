<?php

declare(strict_types=1);

////	scrape_build_where_clause
// Build a parameterized WHERE clause for scraping multiple info_hashes.
// Returns ['where' => clause with `?` placeholders, 'params' => the hashes in
// order]. 'where' is '' (and params empty) when there are no hashes, so callers
// don't accidentally append a bare 'WHERE' to their query. The hashes bind as
// statement parameters instead of being interpolated into the SQL.

/**
 * @param array<int|string, string> $info_hashes
 * @return array{where: string, params: array<int, string>}
 */
function scrape_build_where_clause(array $info_hashes): array
{
    if (empty($info_hashes)) {
        return ['where' => '', 'params' => []];
    }
    $conditions = array_fill(0, count($info_hashes), '`p`.`info_hash`=?');

    return [
        'where' => 'WHERE '.implode(' OR ', $conditions),
        'params' => array_values($info_hashes),
    ];
}
