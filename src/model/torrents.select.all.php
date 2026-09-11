<?php

declare(strict_types=1);

////	torrents_select_all
// Returns torrents — listed and unlisted — with peer counts (seeders/leechers)
// for the management API and the admin list. Mirrors torrents_select_listed()
// but drops its `WHERE t.listed = 1` clause and adds the `user` and `listed`
// columns to the shape. When $user is non-null the result is scoped to that
// owner's torrents (`WHERE t.user = ?`); null returns every torrent, any owner
// (the admin / admin-panel view). Returns an empty array when none match.
//
// $limit null fetches every matching row — what the API and the feed views
// want, since a client asking for the torrent list is asking for all of it. The
// admin listing passes a limit and pages, because rendering a row per torrent
// is unbounded work on a tracker of any size.
//
// $search and $listed go through torrents_filter_sql(), shared with
// torrents_count() so a paged listing counts what it filtered. $sort is a
// whitelist key, not a column name; the seeders/leechers keys order on the
// aggregate, which is why they sort correctly across pages rather than only
// within one.
/**
 * @param PhoenixSettings $settings
 * @return list<array{
 *     info_hash: string|null,
 *     user: string|null,
 *     name: string|null,
 *     size: int,
 *     listed: int,
 *     downloads: int,
 *     seeders: int,
 *     leechers: int,
 *     peers: int,
 *     traffic: int,
 *     filename: string|null,
 *     files: list<array{path: string, length: int}>|null,
 *     trackers: list<string>|null,
 *     webseeds: list<string>|null,
 * }>
 */
function torrents_select_all(
    mysqli $connection,
    array $settings,
    ?string $user = null,
    ?int $limit = null,
    int $offset = 0,
    string $search = '',
    int $listed = -1,
    string $sort = 'seeders',
    string $dir = 'desc',
): array {
    require_once __DIR__.'/../functions/torrent.normalize.meta.php';
    require_once __DIR__.'/torrents.filter.sql.php';

    $prefix = $settings['db_prefix'];
    $offset = max(0, $offset);

    // Whitelist: the key arrives from the query string, the value never does.
    // The peer counts order on the aggregate alias rather than a stored column,
    // so the ranking is the one the page shows.
    $columns = [
        'name' => '`t`.`name`',
        'user' => '`t`.`user`',
        'size' => '`t`.`size`',
        'downloads' => '`t`.`downloads`',
        'listed' => '`t`.`listed`',
        'seeders' => '`seeders`',
        'leechers' => '`leechers`',
        'traffic' => '`t`.`size` * `t`.`downloads`',
    ];
    $order = $columns[$sort] ?? $columns['seeders'];
    $direction = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';

    $filter = torrents_filter_sql($search, $listed);
    $params = $filter['params'];

    // $user is the API's own scoping, applied on top of whatever the listing
    // filtered — the two never contradict, since only the admin page filters.
    $where = $filter['where'];
    if ($user !== null) {
        $where = ($where === '' ? ' WHERE ' : $where.' AND ').'`t`.`user` = ?';
        $params[] = $user;
    }

    $sql = 'SELECT
			`t`.`info_hash` AS `info_hash`,
			`t`.`user` AS `user`,
			`t`.`name` AS `name`,
			`t`.`size` AS `size`,
			`t`.`listed` AS `listed`,
			`t`.`downloads` AS `downloads`,
			IFNULL(SUM(`p`.`state`=\'1\'), 0) AS `seeders`,
			IFNULL(SUM(`p`.`state`=\'0\'), 0) AS `leechers`,
			`t`.`filename` AS `filename`,
			`t`.`files` AS `files`,
			`t`.`trackers` AS `trackers`,
			`t`.`webseeds` AS `webseeds`
		FROM `'.$prefix.'torrents` AS `t`
		LEFT JOIN `'.$prefix.'peers` AS `p` ON `t`.`info_hash` = `p`.`info_hash`'.
        $where.'
		GROUP BY `t`.`info_hash`
		ORDER BY '.$order.' '.$direction.
        // Tie-break on the primary key so paging is stable: without it, rows
        // sharing a sort value can reappear or vanish between pages.
        ', `t`.`info_hash` ASC'.
        ($limit === null ? '' : '
		LIMIT '.max(1, $limit).' OFFSET '.$offset).';';

    $result = mysqli_execute_query($connection, $sql, $params);
    if (! $result instanceof mysqli_result) {
        tracker_error('Unable to get torrents.');
    }

    $torrents = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $meta = torrent_normalize_meta(
            is_string($row['filename']) ? $row['filename'] : null,
            is_string($row['files']) ? $row['files'] : null,
            is_string($row['trackers']) ? $row['trackers'] : null,
            is_string($row['webseeds']) ? $row['webseeds'] : null,
        );

        $torrents[] = [
            'info_hash' => is_string($row['info_hash']) ? $row['info_hash'] : null,
            'user' => is_string($row['user']) ? $row['user'] : null,
            'name' => is_string($row['name']) ? $row['name'] : null,
            'size' => intval($row['size']),
            'listed' => intval($row['listed']),
            'downloads' => intval($row['downloads']),
            'seeders' => intval($row['seeders']),
            'leechers' => intval($row['leechers']),
            'peers' => intval($row['seeders']) + intval($row['leechers']),
            'traffic' => intval($row['size']) * intval($row['downloads']),
            'filename' => $meta['filename'],
            'files' => $meta['files'],
            'trackers' => $meta['trackers'],
            'webseeds' => $meta['webseeds'],
        ];
    }

    return $torrents;
}
