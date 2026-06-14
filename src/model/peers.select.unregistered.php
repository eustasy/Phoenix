<?php

declare(strict_types=1);

////	peers_select_unregistered
// Returns swarms that live only in the peers table — info_hashes with active
// peers but no row in the torrents registry. An open tracker accepts an
// announce for any hash without registering it, so its swarm is otherwise
// invisible to the admin Torrents page (which is FROM torrents). Each entry
// carries seeder/leecher counts so those peers are still counted and shown.
// Ordered by info_hash. Returns an empty array when every swarm is registered.

/**
 * @param PhoenixSettings $settings
 * @return list<array{info_hash: string, seeders: int, leechers: int, peers: int}>
 */
function peers_select_unregistered(mysqli $connection, array $settings): array
{
    // No user input — static SQL, the prefix is trusted config.
    $result = mysqli_query(
        $connection,
        'SELECT
			`p`.`info_hash` AS `info_hash`,
			IFNULL(SUM(`p`.`state`=\'1\'), 0) AS `seeders`,
			IFNULL(SUM(`p`.`state`=\'0\'), 0) AS `leechers`
		FROM `'.$settings['db_prefix'].'peers` AS `p`
		LEFT JOIN `'.$settings['db_prefix'].'torrents` AS `t` ON `p`.`info_hash` = `t`.`info_hash`
		WHERE `t`.`info_hash` IS NULL
		GROUP BY `p`.`info_hash`
		ORDER BY `p`.`info_hash`;',
    );
    if (! $result instanceof mysqli_result) {
        tracker_error('Unable to get swarms.');
    }

    $swarms = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $swarms[] = [
            'info_hash' => is_string($row['info_hash']) ? $row['info_hash'] : '',
            'seeders' => intval($row['seeders']),
            'leechers' => intval($row['leechers']),
            'peers' => intval($row['seeders']) + intval($row['leechers']),
        ];
    }

    return $swarms;
}
