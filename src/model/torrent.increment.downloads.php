<?php

declare(strict_types=1);

////	torrent_increment_downloads
// INSERT ... ON DUPLICATE KEY UPDATE downloads counter.
// Inserts torrent row with downloads=1 if new, or increments existing counter.
// Used when a peer announces event=completed.
// Silently returns true even on failure (non-critical operation).

/** @param PhoenixSettings $settings */
function torrent_increment_downloads(mysqli $connection, array $settings, string $info_hash): true
{
    // Best-effort: the download count must never break an announce. Under the
    // PHP 8.1+ mysqli_report strict default a DB error throws, so swallow it to
    // keep the documented silently-fail contract — otherwise it would escape as
    // an uncaught 500 on the completed-announce path.
    try {
        mysqli_execute_query(
            $connection,
            'INSERT INTO `'.$settings['db_prefix'].'torrents` '.
            '(`info_hash`, `downloads`) '.
            'VALUES (?, 1) '.
            'ON DUPLICATE KEY UPDATE '.
                '`downloads`=`downloads`+1;',
            [$info_hash],
        );
    } catch (mysqli_sql_exception $e) {
        // Best-effort: never break the announce, but surface the failure when
        // report_errors is on so a broken counter is not invisible in prod.
        if ($settings['report_errors']) {
            require_once __DIR__.'/../functions/phoenix.hook.event.php';
            phoenix_hook_event('error', ['throwable' => $e, 'source' => 'torrent_increment_downloads']);
        }
    }

    return true;
}
