<?php

declare(strict_types=1);

////	tasks_select
// Read every row from the tasks table as a name => value map, so the admin
// dashboard can show when each maintenance task last ran. `value` is the Unix
// timestamp task_log() recorded (e.g. name 'clean' => last-clean time).
// Returns an empty array when the table is empty or the query fails.

/**
 * @param PhoenixSettings $settings
 * @return array<string, int>
 */
function tasks_select(mysqli $connection, array $settings): array
{
    $result = mysqli_query(
        $connection,
        'SELECT `name`, `value` FROM `'.$settings['db_prefix'].'tasks`;',
    );
    if (! $result instanceof mysqli_result) {
        return [];
    }

    $tasks = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $tasks[(string) $row['name']] = intval($row['value']);
    }

    return $tasks;
}
