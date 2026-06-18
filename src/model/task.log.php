<?php

declare(strict_types=1);

////	task_log
// Records a maintenance-task run and who triggered it ($source: 'admin', 'cron'
// or 'auto'). Writes the current-state row in `tasks` (REPLACE — one row per
// task, last run + source, never pruned, for the dashboard) and appends a row
// to `task_runs` (the full history, pruned by task_retention). $task and
// $source are controlled literals from the call sites, never request input.
/** @param PhoenixSettings $settings */
function task_log(mysqli $connection, array $settings, string $task, int $value, string $source = 'auto'): bool
{
    $prefix = $settings['db_prefix'];

    // Last-run cache: one row per task. REPLACE overwrites the prior run.
    $last = mysqli_query(
        $connection,
        'REPLACE INTO `'.$prefix.'tasks` (`name`, `value`, `source`) '.
        'VALUES (\''.$task.'\', \''.$value.'\', \''.$source.'\');',
    );

    // History log: append one row per run.
    $history = mysqli_query(
        $connection,
        'INSERT INTO `'.$prefix.'task_runs` (`name`, `value`, `source`) '.
        'VALUES (\''.$task.'\', \''.$value.'\', \''.$source.'\');',
    );

    return $last !== false && $history !== false;
}
