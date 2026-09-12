<?php

declare(strict_types=1);

////	task_runs_count
// Count the maintenance-task history rows, optionally for one task name, so the
// Task History pager counts what the filter matched rather than the whole
// table — otherwise a filtered list pages against an unfiltered total and
// trails off into empty pages. Takes the same $name as task_runs_select().
//
// Returns 0 when the table is empty or the query fails.

/** @param PhoenixSettings $settings */
function task_runs_count(mysqli $connection, array $settings, string $name = ''): int
{
    $where = $name === '' ? '' : ' WHERE `name` = ?';
    $params = $name === '' ? [] : [$name];

    $result = mysqli_execute_query(
        $connection,
        'SELECT COUNT(*) AS `count` FROM `'.$settings['db_prefix'].'task_runs`'.$where.';',
        $params,
    );
    if (! $result instanceof mysqli_result) {
        return 0;
    }
    $row = mysqli_fetch_assoc($result);

    return is_array($row) ? intval($row['count']) : 0;
}
