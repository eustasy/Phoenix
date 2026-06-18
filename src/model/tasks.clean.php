<?php

declare(strict_types=1);

////	tasks_clean
// Remove test/sentinel rows from the task tables, and prune the history log by
// task_retention. The `tasks` last-run cache only sheds sentinels — it is never
// time-pruned, so the dashboard always keeps each task's most recent run. The
// `task_runs` history also drops rows older than task_retention days (when set;
// 0 = keep forever). Returns true on success.
/** @param PhoenixSettings $settings */
function tasks_clean(mysqli $connection, array $settings, int $time): bool
{
    $prefix = $settings['db_prefix'];

    // Last-run cache: sentinels only (never time-pruned).
    $last = mysqli_query(
        $connection,
        'DELETE FROM `'.$prefix.'tasks`'.
        ' WHERE `name` LIKE \'__TEST_%\' OR `name` = \'DELETEME\';',
    );

    // History log: sentinels, plus rows older than task_retention days.
    $sql = 'DELETE FROM `'.$prefix.'task_runs`'.
        ' WHERE `name` LIKE \'__TEST_%\' OR `name` = \'DELETEME\'';
    if ($settings['task_retention'] > 0) {
        $sql .= ' OR `value` < \''.($time - $settings['task_retention'] * 86400).'\'';
    }
    $history = mysqli_query($connection, $sql.';');

    return $last !== false && $history !== false;
}
