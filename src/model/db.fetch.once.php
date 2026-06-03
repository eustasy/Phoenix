<?php

declare(strict_types=1);

////	db_fetch_once
// Executes $sql and returns the first row as an associative array, or false
// when the query failed or returned no rows.
/**
 * @param array<int, mixed> $params
 * @return array<string, float|int|string|null>|false
 */
function db_fetch_once(mysqli $connection, string $sql, array $params = []): array|false
{
    $result = $params === []
        ? mysqli_query($connection, $sql)
        : mysqli_execute_query($connection, $sql, $params);
    if (
        $result instanceof mysqli_result &&
        mysqli_num_rows($result)
    ) {
        return mysqli_fetch_assoc($result) ?? false;
    }

    return false;
}
