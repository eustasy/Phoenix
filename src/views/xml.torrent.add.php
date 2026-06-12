<?php

declare(strict_types=1);

////	view_torrent_add_xml
// Renders the torrent added by the API as XML.
// Caller is responsible for emitting the Content-Type header.
//
// Arguments:
//   $torrent: array with:
//             - user: string
//             - info_hash: string (40-char hex)
//             - name: string|null
//             - size: int
//             - listed: int
//             - filename: string|null            (meta)
//             - files: list|null                 (meta)
//             - trackers: list|null              (meta)
//             - webseeds: list|null              (meta)
//
// The four meta blocks are appended only when their value is non-null, matching
// the element shapes view_index_xml emits; every string is routed through
// xml_escape().
//
// Returns: XML string.
/**
 * @param array{
 *     user: string,
 *     info_hash: string,
 *     name: string|null,
 *     size: int,
 *     listed: int,
 *     filename: string|null,
 *     files: list<array{path: string, length: int}>|null,
 *     trackers: list<string>|null,
 *     webseeds: list<string>|null,
 * } $torrent
 */
function view_torrent_add_xml(array $torrent): string
{
    require_once __DIR__.'/../functions/xml.escape.php';

    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
        '<torrent>'.
        '<user>'.xml_escape($torrent['user']).'</user>'.
        '<info_hash>'.$torrent['info_hash'].'</info_hash>'.
        '<name>'.xml_escape($torrent['name'] ?? '').'</name>'.
        '<size>'.$torrent['size'].'</size>'.
        '<listed>'.$torrent['listed'].'</listed>';

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

    return $xml.'</torrent>';
}
