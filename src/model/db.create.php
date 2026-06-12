<?php

declare(strict_types=1);

////	db_create
// Creates the events, peers, tasks, and torrents tables in the connection's
// currently selected database, using $settings['db_prefix'] as the table
// prefix. The events table is created unconditionally so stat-tracking can
// later be enabled with a config flip alone (see stats_enabled).
//
// Schema lives in sql/<table>.sql so it is lintable, importable manually, and
// kept in one place. Each file uses the literal default prefix `phoenix_`,
// which is rewritten here to whatever the install's actual prefix is.
//
// MyISAM is chosen over InnoDB: the tracker is write-heavy and never needs
// transactions or foreign keys.
/** @param array{db_prefix: string} $settings */
function db_create(mysqli $connection, array $settings, bool $debug = false): bool
{
    require_once __DIR__.'/../functions/db.apply.prefix.php';

    $tables = ['events', 'peers', 'tasks', 'torrents'];

    $failure = false;
    foreach ($tables as $table) {
        $path = __DIR__.'/../../sql/'.$table.'.sql';
        $sql = @file_get_contents($path);
        if ($sql === false) {
            if ($debug) {
                echo 'Could not read schema file "'.$path.'".'.PHP_EOL;
            }
            $failure = true;
            continue;
        }

        // Schema files use the literal default prefix `phoenix_`; rewrite to
        // the install's actual prefix before executing.
        $sql = db_apply_prefix($sql, $settings['db_prefix']);

        $result = mysqli_query($connection, $sql);
        if (! $result) {
            if ($debug) {
                echo 'Error #'.mysqli_errno($connection).': "'.mysqli_error($connection).'" while running "'.$sql.'"'.PHP_EOL;
            }
            $failure = true;
        }
    }

    if ($failure) {
        if ($debug) {
            echo 'Database Creation failed.'.PHP_EOL;
        }

        return false;
    }
    if ($debug) {
        echo 'Database Creation successful.'.PHP_EOL;
    }

    return true;
}
