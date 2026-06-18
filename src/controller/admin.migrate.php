<?php

declare(strict_types=1);

////	admin_migrate_action
//  Handles schema upgrade migration action: creates any tables added since
//  the install (db_create is CREATE TABLE IF NOT EXISTS, so existing tables
//  are untouched), then applies the sql/migrations/ files.
//  Returns message string on completion.

/** @param PhoenixSettings $settings */
function admin_migrate_action(mysqli $connection, array $settings, int $time): string
{
    require_once __DIR__.'/../model/db.create.php';
    require_once __DIR__.'/../model/db.migrate.php';

    if (db_create($connection, $settings) && db_migrate($connection, $settings)) {
        require_once __DIR__.'/../model/task.log.php';
        task_log($connection, $settings, 'migrate', $time, 'admin');

        return 'Your schema has been upgraded.';
    } else {
        return 'Could not upgrade the schema.';
    }
}
