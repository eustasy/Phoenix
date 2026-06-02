<?php

declare(strict_types=1);

////	db_create
// Creates the peers, tasks, and torrents tables in the connection's currently
// selected database, using $settings['db_prefix'] as the table prefix.
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

    $tables = ['peers', 'tasks', 'torrents'];

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
        if ($settings['db_prefix'] !== 'phoenix_') {
            $sql = str_replace('phoenix_', $settings['db_prefix'], $sql);
        }

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
