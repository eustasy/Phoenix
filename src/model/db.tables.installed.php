<?php

declare(strict_types=1);

////	db_tables_installed
// Returns true when every prefixed table in $tables exists in the
// connection's configured database, false otherwise. Defaults to checking
// for the three Phoenix tables (peers/tasks/torrents) used by the admin
// panel's first-run flag.

/**
 * @param PhoenixSettings $settings
 * @param array<int, string> $tables
 */
function db_tables_installed(mysqli $connection, array $settings, array $tables = ['peers', 'tasks', 'torrents']): bool
{
    if (empty($tables)) {
        return true;
    }

    $prefixed = [];
    foreach ($tables as $table) {
        $prefixed[] = '\''.$settings['db_prefix'].$table.'\'';
    }

    $result = mysqli_query(
        $connection,
        'SELECT COUNT(*) AS `count` '.
        'FROM `information_schema`.`TABLES` '.
        'WHERE `TABLE_SCHEMA` = \''.$settings['db_name'].'\' '.
        'AND `TABLE_NAME` IN ('.implode(',', $prefixed).');',
    );
    if (! $result instanceof mysqli_result) {
        return false;
    }
    $row = mysqli_fetch_assoc($result);
    if (! $row) {
        return false;
    }

    return intval($row['count']) === count($tables);
}
