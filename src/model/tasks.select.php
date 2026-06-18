<?php

declare(strict_types=1);

////	tasks_select
// Read the tasks table as a name => {value, source} map, so the admin dashboard
// can show when each maintenance task last ran and who ran it. `value` is the
// Unix timestamp task_log() recorded; `source` is 'admin' / 'cron' / 'auto'
// (empty for rows written before source tracking). Returns an empty array when
// the table is empty or the query fails.

/**
 * @param PhoenixSettings $settings
 * @return array<string, array{value: int, source: string}>
 */
function tasks_select(mysqli $connection, array $settings): array
{
    $result = mysqli_query(
        $connection,
        'SELECT `name`, `value`, `source` FROM `'.$settings['db_prefix'].'tasks`;',
    );
    if (! $result instanceof mysqli_result) {
        return [];
    }

    $tasks = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $tasks[(string) $row['name']] = [
            'value' => intval($row['value']),
            'source' => (string) $row['source'],
        ];
    }

    return $tasks;
}
