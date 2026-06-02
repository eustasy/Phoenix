<?php

declare(strict_types=1);

////	torrents_clean
// Delete test/sentinel torrents from the torrents table.
// Removes rows matching test prefixes or the DELETEME sentinel.
/** @param PhoenixSettings $settings */
function torrents_clean(mysqli $connection, array $settings): bool
{
    $sql = 'DELETE FROM `'.$settings['db_prefix'].'torrents`'.
        ' WHERE `info_hash` LIKE \'__TEST_%\''.
        ' OR `info_hash` = \'DELETEME\''.
        ' OR `name` LIKE \'__TEST_%\''.
        ' OR `name` = \'DELETEME\';';
    $result = mysqli_query($connection, $sql);

    return $result !== false;
}
