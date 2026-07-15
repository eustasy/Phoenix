<?php

declare(strict_types=1);

/**
 * @param PhoenixSettings $settings
 * @param string $source who triggered the run: 'auto' (announce), 'cron', or 'admin'
 */
function task_clean(mysqli $connection, array $settings, int $time, string $source = 'auto'): bool
{
    require_once __DIR__.'/../model/task.log.php';
    require_once __DIR__.'/../model/events.clean.php';
    require_once __DIR__.'/../model/peers.clean.php';
    require_once __DIR__.'/../model/tasks.clean.php';
    require_once __DIR__.'/../model/torrents.clean.php';

    // Remove peers that have not announced within 3x the announce interval.
    // 1x = the normal re-announce window; 2x = one missed announce (grace); 3x = clearly gone.
    // Also purges rows with test-reserved prefixes/values left by the test suite.
    $threshold = $time - ($settings['announce_rec_interval'] * 3);
    $cleaned = peers_clean($connection, $settings, $threshold);

    // Clean tasks and torrents tables (sentinels; task_runs also time-pruned)
    $cleaned = tasks_clean($connection, $settings, $time) && $cleaned;
    $cleaned = torrents_clean($connection, $settings) && $cleaned;

    // Prune the stat-tracking ledger (sentinels, plus rows older than
    // stats_retention days when retention is set)
    $cleaned = events_clean($connection, $settings, $time) && $cleaned;

    if ($cleaned) {
        task_log($connection, $settings, 'clean', $time, $source);
    }

    return $cleaned;

}
