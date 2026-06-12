<?php

declare(strict_types=1);

////	db_migrate
// Runs all idempotent migration files from sql/migrations/ in filename order
// against the connection's currently selected database, using
// $settings['db_prefix'] as the table prefix.
//
// Migration files use the literal default prefix `phoenix_`, which is
// rewritten here to whatever the install's actual prefix is.
//
// Files are designed to be idempotent (ADD COLUMN IF NOT EXISTS, etc.) so
// every file is executed on every call — no applied-migrations bookkeeping
// is required.
/** @param array{db_prefix: string} $settings */
function db_migrate(mysqli $connection, array $settings, bool $debug = false): bool
{
    require_once __DIR__.'/../functions/db.apply.prefix.php';

    $pattern = __DIR__.'/../../sql/migrations/*.sql';
    $files = glob($pattern);
    if ($files === false) {
        if ($debug) {
            echo 'Could not glob migration files from "'.$pattern.'".'.PHP_EOL;
        }

        return false;
    }

    sort($files);

    $failure = false;
    foreach ($files as $path) {
        $sql = @file_get_contents($path);
        if ($sql === false) {
            if ($debug) {
                echo 'Could not read migration file "'.$path.'".'.PHP_EOL;
            }
            $failure = true;
            continue;
        }

        // Migration files use the literal default prefix `phoenix_`; rewrite
        // to the install's actual prefix before executing.
        $sql = db_apply_prefix($sql, $settings['db_prefix']);

        // Strip SQL line comments before splitting so that semicolons inside
        // comment text (e.g. "sql/peers.sql; ADD COLUMN") do not create false
        // split points. Then split on `;` so files with multiple ALTER
        // statements work correctly — mysqli_query() only executes the first
        // statement of a multi-statement string.
        $sql = (string) preg_replace('/^--[^\n]*$/m', '', $sql);
        $statements = explode(';', $sql);
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }

            $result = mysqli_query($connection, $statement);
            if (! $result) {
                if ($debug) {
                    echo 'Error #'.mysqli_errno($connection).': "'.mysqli_error($connection).'" while running "'.$path.'".'.PHP_EOL;
                }
                $failure = true;
            }
        }
    }

    if ($failure) {
        if ($debug) {
            echo 'Database Migration failed.'.PHP_EOL;
        }

        return false;
    }
    if ($debug) {
        echo 'Database Migration successful.'.PHP_EOL;
    }

    return true;
}
