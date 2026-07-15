<?php

declare(strict_types=1);

////	peer_insert
// REPLACE INTO peer (insert or update all fields when peer state has changed).
// Uses REPLACE to handle both new peers and state changes (IP change, seeding transition, etc.).
// Returns true on success, calls tracker_error() on failure.

/**
 * @param PhoenixSettings $settings
 * @param array<string, mixed> $peer
 */
function peer_insert(mysqli $connection, array $settings, int $time, array $peer): true
{

    $compactv4 = '';
    $compactv6 = '';
    if (! empty($peer['ipv4']) && is_string($peer['ipv4'])) {
        // BEP 23: compact IPv4 peer = 4-byte big-endian IP + 2-byte big-endian port (6 bytes).
        // Stored as hex so it survives the latin1 DB column without corruption.
        $compactv4 = bin2hex(pack('Nn', ip2long($peer['ipv4']), $peer['portv4']));
    }
    if (! empty($peer['ipv6']) && is_string($peer['ipv6'])) {
        // BEP 7: compact IPv6 peer = 16-byte address (inet_pton) + 2-byte big-endian port (18 bytes).
        $compactv6 = bin2hex(inet_pton($peer['ipv6']).pack('n', $peer['portv6']));
    }

    // Values bind as statement parameters (mysqli_execute_query treats each as a
    // string). Table/column names cannot be bound, so db_prefix stays
    // interpolated (operator config).
    //
    // A DB error here — e.g. an out-of-range value under strict mode (the PHP
    // 8.1+ mysqli_report default) — degrades to a graceful tracker_error()
    // rather than an uncaught 500 on the announce hot path, mirroring torrent_add.
    try {
        $peer_new = mysqli_execute_query(
            $connection,
            'REPLACE INTO `'.$settings['db_prefix'].'peers` '.
            '(`info_hash`, `peer_id`, `compactv4`, `compactv6`, `ipv4`, `ipv6`, `portv4`, `portv6`, `uploaded`, `downloaded`, `left`, `state`, `updated`) '.
            'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $peer['info_hash'],   // 40-byte info_hash in HEX
                $peer['peer_id'],     // 40-byte peer_id in HEX
                $compactv4,           // compacted peer info (hex)
                $compactv6,
                $peer['ipv4'],        // dotted-decimal / colon-hex IP strings
                $peer['ipv6'],
                // A family with no address carries a `false` port (e.g. the
                // other family was dropped for an out-of-range port). The port
                // columns are NOT NULL smallint, and mysqli would bind `false`
                // as '' — which a strict-mode server rejects — so normalise an
                // absent port to 0 here.
                is_int($peer['portv4']) ? $peer['portv4'] : 0,
                is_int($peer['portv6']) ? $peer['portv6'] : 0,
                $peer['uploaded'],    // transfer counters
                $peer['downloaded'],
                $peer['left'],        // integer left (may be the -1 "unknown" sentinel)
                $peer['state'],       // integer state
                $time,                // unix timestamp
            ],
        );
    } catch (mysqli_sql_exception $e) {
        if ($settings['report_errors']) {
            require_once __DIR__.'/../functions/phoenix.hook.event.php';
            phoenix_hook_event('error', ['throwable' => $e, 'source' => 'peer_insert']);
        }
        $peer_new = false;
    }

    if (! $peer_new) {
        tracker_error('Failed to add new peer.');
    }

    return true;
}
