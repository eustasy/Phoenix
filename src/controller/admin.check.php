<?php

declare(strict_types=1);

////	admin_check_action
//  Handles the DB Utilities "Check tables" action (process=check). Runs an
//  integrity scan over every Phoenix table and reports the outcome.
//  A failure here is the interesting result, so it says so plainly rather than
//  folding into a generic error.
//  Returns message string on completion.

/** @param PhoenixSettings $settings */
function admin_check_action(mysqli $connection, array $settings, int $time): string
{
    require_once __DIR__.'/../model/db.check.php';

    if (db_check($connection, $settings, $time, 'admin')) {
        return 'All tables checked and reported no errors.';
    }

    return 'A table reported an error during check. See the server error log.';
}
