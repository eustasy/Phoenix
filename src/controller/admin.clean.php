<?php

declare(strict_types=1);

////	admin_clean_action
//  Handles the DB Utilities "Prune" action: drops stale peers, expired events and
//  task history, and the sentinel rows the test suite leaves behind.
//  Returns message string on completion.

/** @param PhoenixSettings $settings */
function admin_clean_action(mysqli $connection, array $settings, int $time): string
{
    require_once __DIR__.'/../functions/task.clean.php';

    if (task_clean($connection, $settings, $time, 'admin')) {
        return 'Expired rows have been pruned.';
    } else {
        return 'Could not prune the expired rows.';
    }
}
