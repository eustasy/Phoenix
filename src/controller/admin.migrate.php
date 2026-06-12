<?php

declare(strict_types=1);

////	admin_migrate_action
//  Handles schema upgrade migration action.
//  Returns message string on completion.

/** @param PhoenixSettings $settings */
function admin_migrate_action(mysqli $connection, array $settings, int $time): string
{
    require_once __DIR__.'/../model/db.migrate.php';

    if (db_migrate($connection, $settings)) {
        require_once __DIR__.'/../model/task.log.php';
        task_log($connection, $settings, 'migrate', $time);

        return 'Your schema has been upgraded.';
    } else {
        return 'Could not upgrade the schema.';
    }
}
