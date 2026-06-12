<?php

declare(strict_types=1);

////	torrents_select_listed
// Returns all listed torrents with peer counts (seeders/leechers) for the public index.
// Returns an empty array if no listed torrents exist.
/**
 * @param PhoenixSettings $settings
 * @return list<array{
 *     info_hash: string|null,
 *     name: string|null,
 *     size: int,
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
function torrents_select_listed(mysqli $connection, array $settings): array
{
    require_once __DIR__.'/../functions/torrent.normalize.meta.php';

    $sql = 'SELECT
			`t`.`info_hash` AS `info_hash`,
			`t`.`name` AS `name`,
			`t`.`size` AS `size`,
			`t`.`downloads` AS `downloads`,
			IFNULL(SUM(`p`.`state`=\'1\'), 0) AS `seeders`,
			IFNULL(SUM(`p`.`state`=\'0\'), 0) AS `leechers`,
			`t`.`filename` AS `filename`,
			`t`.`files` AS `files`,
			`t`.`trackers` AS `trackers`,
			`t`.`webseeds` AS `webseeds`
		FROM `'.$settings['db_prefix'].'torrents` AS `t`
		LEFT JOIN `'.$settings['db_prefix'].'peers` AS `p` ON `t`.`info_hash` = `p`.`info_hash`
		WHERE `t`.`listed` = 1
		GROUP BY `t`.`info_hash`
		ORDER BY `t`.`name`;';

    $result = mysqli_query($connection, $sql);
    if (! $result instanceof mysqli_result) {
        tracker_error('Unable to get index.');
    }

    $index = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $meta = torrent_normalize_meta(
            is_string($row['filename']) ? $row['filename'] : null,
            is_string($row['files']) ? $row['files'] : null,
            is_string($row['trackers']) ? $row['trackers'] : null,
            is_string($row['webseeds']) ? $row['webseeds'] : null,
        );

        $index[] = [
            'info_hash' => is_string($row['info_hash']) ? $row['info_hash'] : null,
            'name' => is_string($row['name']) ? $row['name'] : null,
            'size' => intval($row['size']),
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

    return $index;
}
