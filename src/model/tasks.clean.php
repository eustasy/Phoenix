<?php

declare(strict_types=1);

////	tasks_clean
// Delete test/sentinel rows from the tasks table.
// Removes rows matching test prefixes or the DELETEME sentinel.
/** @param PhoenixSettings $settings */
function tasks_clean(mysqli $connection, array $settings): bool
{
    $sql = 'DELETE FROM `'.$settings['db_prefix'].'tasks`'.
        ' WHERE `name` LIKE \'__TEST_%\''.
        ' OR `name` = \'DELETEME\';';
    $result = mysqli_query($connection, $sql);

    return $result !== false;
}
