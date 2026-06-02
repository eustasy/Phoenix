<?php

declare(strict_types=1);

////	admin_optimize_action
//  Handles database optimization action.
//  Returns message string on completion.

/** @param array<string, mixed> $settings */
function admin_optimize_action(mysqli $connection, array $settings, int $time): string
{
    require_once __DIR__.'/../model/db.optimize.php';

    if (db_optimize($connection, $settings, $time)) {
        return 'Your MySQL Tracker Database has been optimized.';
    } else {
        return 'Could not optimize the MySQL Database.';
    }
}
