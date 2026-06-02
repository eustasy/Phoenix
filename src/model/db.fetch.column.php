<?php

declare(strict_types=1);

////	db_fetch_column
// Executes $sql and returns the first column of every result row as a flat indexed array.
function db_fetch_column(mysqli $connection, string $sql): array
{
    $result = mysqli_query($connection, $sql);
    if (! $result) {
        tracker_error('Failed to build array.');
    }
    $response = [];
    while ($thing = mysqli_fetch_array($result)) {
        $response[] = $thing[0];
    }

    return $response;
}
