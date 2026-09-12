<?php

declare(strict_types=1);

////	admin_analyze_action
//  Handles the Utilities "Analyze tables" action (process=analyze). Refreshes
//  index statistics so the query planner keeps choosing sensible plans.
//  Cheap and non-destructive — it reclaims no space and rebuilds nothing; that
//  is what Optimize is for.
//  Returns message string on completion.

/** @param PhoenixSettings $settings */
function admin_analyze_action(mysqli $connection, array $settings, int $time): string
{
    require_once __DIR__.'/../model/db.analyze.php';

    if (db_analyze($connection, $settings, $time, 'admin')) {
        return 'Table statistics have been refreshed.';
    }

    return 'Could not analyze the tables. See the server error log.';
}
