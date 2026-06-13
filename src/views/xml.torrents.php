<?php

declare(strict_types=1);

////	view_torrents_xml
// Renders a collection of torrents (from torrents_select_all()) as XML.
// Caller is responsible for emitting the Content-Type header.
//
// Input: $torrents — a list of rows in the torrents_select_all() shape.
// Output: XML string — <torrents> with one <torrent> per row. Each <torrent>
//         carries swarm stats plus the ownership/visibility columns (`user`,
//         `listed`); the four meta blocks are appended only when their value is
//         non-null, matching view_index_xml's element shapes. Every string is
//         routed through xml_escape().

/**
 * @param list<array{
 *     info_hash: string|null,
 *     user: string|null,
 *     name: string|null,
 *     size: int,
 *     listed: int,
 *     downloads: int,
 *     seeders: int,
 *     leechers: int,
 *     peers: int,
 *     traffic: int,
 *     filename: string|null,
 *     files: list<array{path: string, length: int}>|null,
 *     trackers: list<string>|null,
 *     webseeds: list<string>|null,
 * }> $torrents
 */
function view_torrents_xml(array $torrents): string
{
    require_once __DIR__.'/../functions/xml.escape.php';

    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><torrents>';
    foreach ($torrents as $torrent) {
        $xml .= '<torrent>'.
            '<info_hash>'.$torrent['info_hash'].'</info_hash>'.
            '<user>'.xml_escape($torrent['user'] ?? '').'</user>'.
            '<name>'.xml_escape($torrent['name'] ?? '').'</name>'.
            '<size>'.$torrent['size'].'</size>'.
            '<listed>'.$torrent['listed'].'</listed>'.
            '<downloads>'.$torrent['downloads'].'</downloads>'.
            '<seeders>'.$torrent['seeders'].'</seeders>'.
            '<leechers>'.$torrent['leechers'].'</leechers>'.
            '<peers>'.$torrent['peers'].'</peers>'.
            '<traffic>'.$torrent['traffic'].'</traffic>';

        ////	filename
        // Emit only when the value is non-null.
        if ($torrent['filename'] !== null) {
            $xml .= '<filename>'.xml_escape($torrent['filename']).'</filename>';
        }

        ////	files
        // Emit a <files> block with one <file> per entry when non-null.
        if ($torrent['files'] !== null) {
            $xml .= '<files>';
            foreach ($torrent['files'] as $file) {
                $xml .= '<file>'.
                    '<path>'.xml_escape($file['path']).'</path>'.
                    '<length>'.$file['length'].'</length>'.
                    '</file>';
            }
            $xml .= '</files>';
        }

        ////	trackers
        // Emit a <trackers> block with one <tracker> per URL when non-null.
        if ($torrent['trackers'] !== null) {
            $xml .= '<trackers>';
            foreach ($torrent['trackers'] as $tracker) {
                $xml .= '<tracker>'.xml_escape($tracker).'</tracker>';
            }
            $xml .= '</trackers>';
        }

        ////	webseeds
        // Emit a <webseeds> block with one <webseed> per URL when non-null.
        if ($torrent['webseeds'] !== null) {
            $xml .= '<webseeds>';
            foreach ($torrent['webseeds'] as $webseed) {
                $xml .= '<webseed>'.xml_escape($webseed).'</webseed>';
            }
            $xml .= '</webseeds>';
        }

        $xml .= '</torrent>';
    }

    return $xml.'</torrents>';
}
