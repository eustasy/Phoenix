<?php

declare(strict_types=1);

////	view_index_xml
// Renders a normalized $index array as XML torrent index response.
// Caller is responsible for emitting the Content-Type header.
//
// Arguments:
//   $index: array of torrents, each with:
//           - info_hash: string (40-char hex)
//           - name: string
//           - size: int
//           - downloads: int
//           - seeders: int
//           - leechers: int
//           - peers: int
//           - traffic: int
//
// Returns: XML string.
/** @param list<array{info_hash: string|null, name: string|null, size: int, downloads: int, seeders: int, leechers: int, peers: int, traffic: int}> $index */
function view_index_xml(array $index): string
{
    require_once __DIR__.'/../functions/xml.escape.php';

    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><torrents>';
    foreach ($index as $torrent) {
        $xml .= '<torrent>'.
            '<info_hash>'.$torrent['info_hash'].'</info_hash>'.
            '<name>'.xml_escape($torrent['name'] ?? '').'</name>'.
            '<size>'.$torrent['size'].'</size>'.
            '<downloads>'.$torrent['downloads'].'</downloads>'.
            '<seeders>'.$torrent['seeders'].'</seeders>'.
            '<leechers>'.$torrent['leechers'].'</leechers>'.
            '<peers>'.$torrent['peers'].'</peers>'.
            '<traffic>'.$torrent['traffic'].'</traffic>'.
        '</torrent>';
    }

    return $xml.'</torrents>';
}
