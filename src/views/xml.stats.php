<?php

declare(strict_types=1);

////	view_stats_xml
// Renders tracker statistics as XML.
// Caller is responsible for emitting the Content-Type header.
//
// Arguments:
//   $stats: array with:
//           - peers: int
//           - seeders: int
//           - leechers: int
//           - torrents: int
//           - downloads: int
//           - traffic: int
//   $settings: config array (needs phoenix_version)
//
// Returns: XML string.
/**
 * @param array<string, int> $stats
 * @param array<string, mixed> $settings
 */
function view_stats_xml(array $stats, array $settings): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
        '<tracker version="$Id: '.$settings['phoenix_version'].' $">'.
        '<peers>'.$stats['peers'].'</peers>'.
        '<seeders>'.$stats['seeders'].'</seeders>'.
        '<leechers>'.$stats['leechers'].'</leechers>'.
        '<torrents>'.$stats['torrents'].'</torrents>'.
        '<downloads>'.$stats['downloads'].'</downloads>'.
        '<traffic>'.$stats['traffic'].'</traffic>'.
        '</tracker>';
}
