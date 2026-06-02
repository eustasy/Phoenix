<?php

declare(strict_types=1);

////	db_fetch_once
// Executes $sql and returns the first row as an associative array, or false
// when the query failed or returned no rows.
function db_fetch_once(mysqli $connection, string $sql): array|false
{
    $result = mysqli_query($connection, $sql);
    if (
        $result &&
        mysqli_num_rows($result)
    ) {
        return mysqli_fetch_assoc($result) ?? false;
    }

    return false;
}
