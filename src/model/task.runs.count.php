<?php

declare(strict_types=1);

////	task_runs_count
// Count the maintenance-task history rows, optionally for one task name, so the
// Task History pager counts what the filter matched rather than the whole
// table — otherwise a filtered list pages against an unfiltered total and
// trails off into empty pages. Takes the same $name and $source as
// task_runs_select(), through the same task_runs_filter_sql().
//
// Returns 0 when the table is empty or the query fails.

/** @param PhoenixSettings $settings */
function task_runs_count(mysqli $connection, array $settings, string $name = '', string $source = ''): int
{
    require_once __DIR__.'/task.runs.filter.sql.php';

    $filter = task_runs_filter_sql($name, $source);

    $result = mysqli_execute_query(
        $connection,
        'SELECT COUNT(*) AS `count` FROM `'.$settings['db_prefix'].'task_runs`'.$filter['where'].';',
        $filter['params'],
    );
    if (! $result instanceof mysqli_result) {
        return 0;
    }
    $row = mysqli_fetch_assoc($result);

    return is_array($row) ? intval($row['count']) : 0;
}
