<?php

declare(strict_types=1);

////	db_maintenance
// Run one maintenance statement (CHECK / ANALYZE / OPTIMIZE) over a list of
// tables and report whether every one of them actually succeeded. The shared
// engine behind db_check(), db_analyze() and db_optimize().
//
// Reading the results is the point. These statements do not fail the way a
// normal query does: a problem comes back as a ROW inside the result set —
// `Msg_type` of `Error`, then a `status` row saying `Operation failed` — while
// mysqli_errno() stays 0 and mysqli_multi_query() still returns true. Returning
// that value, as this used to, meant a rebuild that died partway (a full disk
// is the realistic case) was indistinguishable from a clean run, and got logged
// as a success. So each result set is walked and any `Error` row fails the run.
//
// $op is a controlled literal from the three callers, never request input.
// Table names are built from db_prefix and a fixed list for the same reason:
// identifiers cannot be bound as parameters.
//
// Returns true only when every statement reported no error.

/**
 * @param PhoenixSettings $settings
 * @param list<string> $tables unprefixed table names
 */
function db_maintenance(mysqli $connection, array $settings, string $op, array $tables): bool
{
    if ($tables === []) {
        // Nothing to do is not a failure — and an empty statement string would
        // make mysqli_multi_query() return false, which would say otherwise.
        return true;
    }

    $sql = '';
    foreach ($tables as $table) {
        $sql .= $op.' TABLE `'.$settings['db_prefix'].$table.'`;';
    }

    $previous = mysqli_report(MYSQLI_REPORT_OFF);
    $ok = mysqli_multi_query($connection, $sql);

    if ($ok) {
        do {
            $result = mysqli_store_result($connection);
            if (! $result) {
                continue;
            }
            while ($row = mysqli_fetch_assoc($result)) {
                // 'note' and 'status' are routine — OPTIMIZE always notes that
                // InnoDB does a recreate instead. Only 'Error' is a failure.
                if (strcasecmp((string) ($row['Msg_type'] ?? ''), 'error') === 0) {
                    $ok = false;
                }
            }
            mysqli_free_result($result);
        } while (mysqli_next_result($connection));
    }

    mysqli_report($previous);

    return $ok;
}
