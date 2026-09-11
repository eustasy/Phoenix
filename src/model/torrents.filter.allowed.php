<?php

declare(strict_types=1);

////	torrents_filter_allowed
// Of the info_hashes asked for, which are registered on this tracker — the
// closed-tracker (BEP 27) permission check for both announce and scrape.
//
// Replaces loading every info_hash into PHP and searching that array. The old
// shape cost 0.24 KB and 0.29ms of CPU per torrent on EVERY announce (48.8 MB
// and 57ms at 200k torrents), because it read the whole table to answer a
// question about one hash. This asks the index instead: constant memory, and
// the only part of the announce path that scaled with torrent count is gone.
//
// Order is preserved from $info_hashes, because a multi-hash scrape replies in
// the order it was asked. Duplicates collapse — the response is keyed by hash,
// so asking twice was always one answer.
//
// An empty $info_hashes returns empty without querying. Callers treat an empty
// result as "none of these is allowed": announce rejects, and scrape errors
// rather than falling through to a full scrape, which is what stops a caller
// enumerating the tracker by asking for hashes it may not see.

/**
 * @param PhoenixSettings $settings
 * @param array<int, string> $info_hashes 40-char hex, already sanitized
 * @return array<int, string>
 */
function torrents_filter_allowed(mysqli $connection, array $settings, array $info_hashes): array
{
    $info_hashes = array_values(array_unique(array_filter(
        $info_hashes,
        static fn (string $hash): bool => $hash !== '',
    )));
    if ($info_hashes === []) {
        return [];
    }

    // One placeholder per hash. The count comes from the request, so it is
    // bounded by the web server's request line (~160 hashes in a typical 8 KB),
    // and every value is bound rather than interpolated.
    $placeholders = implode(', ', array_fill(0, count($info_hashes), '?'));

    $result = mysqli_execute_query(
        $connection,
        'SELECT `info_hash` FROM `'.$settings['db_prefix'].'torrents` '.
        'WHERE `info_hash` IN ('.$placeholders.');',
        $info_hashes,
    );
    if (! $result instanceof mysqli_result) {
        tracker_error('Unable to check torrents.');
    }

    $allowed = [];
    while ($row = mysqli_fetch_assoc($result)) {
        if (is_string($row['info_hash'])) {
            $allowed[$row['info_hash']] = true;
        }
    }

    // Back into the caller's order rather than the database's.
    return array_values(array_filter(
        $info_hashes,
        static fn (string $hash): bool => isset($allowed[$hash]),
    ));
}
