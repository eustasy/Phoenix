<?php

declare(strict_types=1);

////	view_announce_bencode
// Renders a BitTorrent announce response as bencode (BEP 3).
//
// Arguments:
//   $counts: array{complete: int, incomplete: int} — swarm counts
//   $settings: config array (needs announce_interval, min_interval)
//   $rows: array of peer rows from peers_select_active()
//   $compact: bool — whether client requested compact mode (BEP 23)
//   $no_peer_id: bool — whether client requested peer_id omission
//   $external_ip: string|false — client's own public address to echo back per
//     BEP 24 (packed to raw bytes here), or false to omit the key
//
// In compact mode, $rows must have 'compactv4' and 'compactv6' hex columns.
// In non-compact mode, $rows must have 'ipv4', 'portv4', 'ipv6', 'portv6',
// and 'peer_id' columns.
//
// The response is assembled as a plain PHP structure and handed to
// bencode_encode(), which owns length prefixes and lexicographic key order —
// the 'peers'/'peers6' ordering falls out of the sort automatically.
/**
 * @param array{complete: int, incomplete: int} $counts
 * @param PhoenixSettings $settings
 * @param array<int, array<string, float|int|string|null>> $rows
 */
function view_announce_bencode(
    array $counts,
    array $settings,
    array $rows,
    bool $compact,
    bool $no_peer_id,
    string|false $external_ip = false,
): string {
    // Helpers loaded inside the function so coverage tracks them per-call
    // (top-of-file require_once executes once per process and may show as
    // uncovered if another test loaded the file first).
    require_once __DIR__.'/../functions/bencode.encode.php';
    require_once __DIR__.'/../functions/peer.format.dict.php';
    require_once __DIR__.'/../functions/peers.format.compact.php';

    $response = [
        'complete' => (int) $counts['complete'],
        'incomplete' => (int) $counts['incomplete'],
        'interval' => $settings['announce_interval'],
        'min interval' => $settings['min_interval'],
    ];

    // BEP 24: echo the client's own public address back so a NATed peer can
    // learn how the tracker sees it. inet_pton packs it to the raw 4-byte
    // (IPv4) or 16-byte (IPv6) form, bencoded as a byte string.
    if ($external_ip !== false) {
        $packed = inet_pton($external_ip);
        if ($packed !== false) {
            $response['external ip'] = $packed;
        }
    }

    if ($compact) {
        // BEP 23 (IPv4, 6 bytes per peer) and BEP 7 (IPv6, 18 bytes per peer).
        // peers_format_compact does the hex2bin assembly; the raw binary blobs
        // are bencoded as plain byte strings.
        $compact_peers = peers_format_compact($rows);
        $response['peers'] = $compact_peers['v4'];
        $response['peers6'] = $compact_peers['v6'];
    } else {
        // Non-compact mode: 'peers' is a list of peer dictionaries. Rows with
        // no usable address return null from peer_format_dict and are skipped.
        $peers = [];
        foreach ($rows as $row) {
            $peer = peer_format_dict($row, ! $no_peer_id);
            if ($peer !== null) {
                $peers[] = $peer;
            }
        }
        $response['peers'] = $peers;
    }

    return bencode_encode($response);
}
