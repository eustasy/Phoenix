<?php

declare(strict_types=1);

////	peers_count
// Count active peer rows across every swarm — the figure behind the Peers
// sidebar badge. A peer announcing to several torrents counts once per swarm
// (it is a distinct peers row each time), matching the seeders+leechers totals
// shown elsewhere. Returns 0 when the table is empty or the query fails.

/** @param PhoenixSettings $settings */
function peers_count(mysqli $connection, array $settings): int
{
    require_once __DIR__.'/db.fetch.once.php';

    $row = db_fetch_once(
        $connection,
        'SELECT COUNT(*) AS `count` FROM `'.$settings['db_prefix'].'peers`;',
    );

    return $row === false ? 0 : intval($row['count']);
}
