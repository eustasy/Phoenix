<?php

declare(strict_types=1);

////	admin_migrate_action
//  Handles schema upgrade migration action: creates any tables added since
//  the install (db_create is CREATE TABLE IF NOT EXISTS, so existing tables
//  are untouched), then applies the sql/migrations/ files. As of 5.0 that
//  directory ships empty — db_create alone produces the finished schema — so
//  this is a no-op that reports success until the first 5.x migration lands.
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
