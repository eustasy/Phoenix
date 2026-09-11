<?php

declare(strict_types=1);

////	peers_filter_sql
// Build the shared WHERE clause behind the admin Peers listing, so
// peers_select_all() and peers_count() filter identically — a mismatch would
// page a filtered list against an unfiltered total and silently produce empty
// trailing pages.
//
// Both callers join torrents as `t` and alias peers as `p`, so the returned
// clause may reference either. Values are returned as bound parameters, never
// interpolated: $search is an untrusted string straight off the query string.
//
// What is searchable is limited by what is actually stored. `client` and
// `country` are derived per-request in PHP — the client label from peer_id, the
// country from the IP via GeoIP — and neither derived value is kept as a
// column, so neither can be reached from SQL. Address, info_hash and torrent
// name can.
//
// $state is 1 (seeding), 0 (leeching), or -1 for either. $info_hash narrows to
// one swarm — the per-torrent drill-down is this filter applied to the same
// paged listing, rather than a second unpaged view that would render every peer
// of a large swarm at once.
//
// Returns ['where' => string (empty or leading " WHERE "), 'params' => list].

/** @return array{where: string, params: list<string|int>} */
function peers_filter_sql(string $search, int $state = -1, string $info_hash = ''): array
{
    $clauses = [];
    $params = [];

    $search = trim($search);
    if ($search !== '') {
        // Escape the LIKE wildcards themselves, so a literal % or _ in a search
        // term matches itself rather than everything.
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
        $clauses[] = '(p.`ipv4` LIKE ? OR p.`ipv6` LIKE ? OR p.`info_hash` LIKE ? OR t.`name` LIKE ?)';
        array_push($params, $like, $like, $like, $like);
    }

    if ($state === 0 || $state === 1) {
        $clauses[] = 'p.`state` = ?';
        $params[] = $state;
    }

    if ($info_hash !== '') {
        $clauses[] = 'p.`info_hash` = ?';
        $params[] = $info_hash;
    }

    return [
        'where' => $clauses === [] ? '' : ' WHERE '.implode(' AND ', $clauses),
        'params' => $params,
    ];
}
