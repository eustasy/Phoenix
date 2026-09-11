<?php

declare(strict_types=1);

////	events_geo_any
// Whether the events ledger holds any geo-tagged completion — the cheap
// question behind "should the Geography page offer the ledger metrics?".
//
// The page used to answer it by running events_geo_counts() and testing the
// result, which means scanning the whole ledger to decide whether to show a
// button. This stops at the first matching row: with the `geo` index
// (event, country, info_hash) it is a seek, not a scan.
//
// Returns false when the ledger is empty of geo-tagged completions, or on
// error — the caller treats that as "not available", which is the safe read.

/** @param PhoenixSettings $settings */
function events_geo_any(mysqli $connection, array $settings): bool
{
    $result = mysqli_query(
        $connection,
        'SELECT 1 FROM `'.$settings['db_prefix'].'events` '.
        'WHERE `event` = \'completed\' AND `country` <> \'\' LIMIT 1;',
    );

    return $result instanceof mysqli_result && mysqli_num_rows($result) > 0;
}
