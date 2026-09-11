<?php

declare(strict_types=1);

////	torrents_filter_sql
// Build the shared WHERE clause behind the admin Torrents listing, so
// torrents_select_all() and torrents_count() filter identically — a mismatch
// would page a filtered list against an unfiltered total and silently produce
// empty trailing pages. The peers-side twin is peers_filter_sql().
//
// Callers alias torrents as `t`, so the returned clause references that alone —
// which is what lets torrents_count() skip the peers join entirely. Values come
// back as bound parameters, never interpolated: $search is an untrusted string
// straight off the query string.
//
// Everything the old client-side filter matched on is reachable here, including
// the four meta fields the table does not render as columns (filename, the file
// list, trackers, webseeds). They were searchable when the whole table was in
// the browser; paging the listing would otherwise have quietly taken that away.
// `files`, `trackers` and `webseeds` are stored as JSON text, so a LIKE matches
// inside the encoded list — good enough to find a path or a tracker host, and
// far cheaper than parsing every row.
//
// $listed is 1 (listed), 0 (unlisted), or -1 for either.
//
// Returns ['where' => string (empty or leading " WHERE "), 'params' => list].

/** @return array{where: string, params: list<string|int>} */
function torrents_filter_sql(string $search, int $listed = -1): array
{
    $clauses = [];
    $params = [];

    $search = trim($search);
    if ($search !== '') {
        // Escape the LIKE wildcards themselves, so a literal % or _ in a search
        // term matches itself rather than everything.
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
        $columns = ['name', 'user', 'info_hash', 'filename', 'files', 'trackers', 'webseeds'];
        $ors = [];
        foreach ($columns as $column) {
            $ors[] = 't.`'.$column.'` LIKE ?';
            $params[] = $like;
        }
        $clauses[] = '('.implode(' OR ', $ors).')';
    }

    if ($listed === 0 || $listed === 1) {
        $clauses[] = 't.`listed` = ?';
        $params[] = $listed;
    }

    return [
        'where' => $clauses === [] ? '' : ' WHERE '.implode(' AND ', $clauses),
        'params' => $params,
    ];
}
