<?php

declare(strict_types=1);

////	task_runs_select
// Read a page of the maintenance-task history, newest first, optionally
// filtered to one task name. This is the only reader of `task_runs`: task_log()
// appends a row per run and tasks_clean() prunes past task_retention, but until
// the Task History page nothing ever read the table back.
//
// $name filters to one task ('clean', 'optimize', 'backup', 'migrate',
// 'install'); '' returns every task. It is bound as a parameter, never
// interpolated, even though the call sites pass controlled values.
//
// Returns a list of ['id' => int, 'name' => string, 'value' => int (Unix
// timestamp of the run), 'source' => string ('admin'|'cron'|'auto', '' on rows
// written before source tracking)], or an empty list when the query fails.

/**
 * @param PhoenixSettings $settings
 * @return list<array{id: int, name: string, value: int, source: string}>
 */
function task_runs_select(mysqli $connection, array $settings, string $name = '', int $limit = 100, int $offset = 0): array
{
    // LIMIT/OFFSET are ints from the caller, clamped rather than bound: mysqli
    // cannot bind a LIMIT placeholder on every server version.
    $limit = max(1, min(1000, $limit));
    $offset = max(0, $offset);

    $where = $name === '' ? '' : ' WHERE `name` = ?';
    $params = $name === '' ? [] : [$name];

    $result = mysqli_execute_query(
        $connection,
        'SELECT `id`, `name`, `value`, `source` FROM `'.$settings['db_prefix'].'task_runs`'.
        $where.
        // id as the tiebreaker: several tasks can share a timestamp when one
        // cron run logs a clean and an optimize in the same second.
        ' ORDER BY `value` DESC, `id` DESC LIMIT '.$limit.' OFFSET '.$offset.';',
        $params,
    );
    if (! $result instanceof mysqli_result) {
        return [];
    }

    $runs = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $runs[] = [
            'id' => intval($row['id']),
            'name' => (string) $row['name'],
            'value' => intval($row['value']),
            'source' => (string) $row['source'],
        ];
    }

    return $runs;
}
