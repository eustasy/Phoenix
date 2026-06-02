<?php

declare(strict_types=1);

////	torrents_select_listed
// Returns all listed torrents with peer counts (seeders/leechers) for the public index.
// Returns an empty array if no listed torrents exist.
/**
 * @param array<string, mixed> $settings
 * @return list<array<string, mixed>>
 */
function torrents_select_listed(mysqli $connection, array $settings): array
{
    $sql = 'SELECT
			`t`.`info_hash` AS `info_hash`,
			`t`.`name` AS `name`,
			`t`.`size` AS `size`,
			`t`.`downloads` AS `downloads`,
			IFNULL(SUM(`p`.`state`=\'1\'), 0) AS `seeders`,
			IFNULL(SUM(`p`.`state`=\'0\'), 0) AS `leechers`
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
        $index[] = [
            'info_hash' => $row['info_hash'],
            'name' => $row['name'],
            'size' => intval($row['size']),
            'downloads' => intval($row['downloads']),
            'seeders' => intval($row['seeders']),
            'leechers' => intval($row['leechers']),
            'peers' => intval($row['seeders']) + intval($row['leechers']),
            'traffic' => intval($row['size']) * intval($row['downloads']),
        ];
    }

    return $index;
}
