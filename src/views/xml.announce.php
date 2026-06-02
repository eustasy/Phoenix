<?php

declare(strict_types=1);

////	view_announce_xml
// Renders a BitTorrent announce response as XML (for debugging/monitoring).
// Caller is responsible for emitting the Content-Type header.
//
// Arguments:
//   $counts: array{complete: int, incomplete: int} — swarm counts
//   $settings: config array (needs announce_interval, min_interval)
//   $rows: array of peer rows from peers_select_active()
//
// Returns: XML string.
function view_announce_xml(array $counts, array $settings, array $rows): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
        '<announce>'.
        '<complete>'.$counts['complete'].'</complete>'.
        '<incomplete>'.$counts['incomplete'].'</incomplete>'.
        '<interval>'.$settings['announce_interval'].'</interval>'.
        '<min_interval>'.$settings['min_interval'].'</min_interval>'.
        '<peers>';

    foreach ($rows as $row) {
        $xml .= '<peer>';
        if ($row['ipv4'] != null) {
            $xml .= '<ip>'.$row['ipv4'].'</ip>'.
                '<port>'.$row['portv4'].'</port>';
        } elseif ($row['ipv6'] != null) {
            $xml .= '<ip>'.$row['ipv6'].'</ip>'.
                '<port>'.$row['portv6'].'</port>';
        }
        $xml .= '<peer_id>'.$row['peer_id'].'</peer_id>'.
            '</peer>';
    }

    return $xml.'</peers></announce>';
}
