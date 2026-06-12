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
//           - filename: string|null    (meta, present in rows from torrents_select_listed)
//           - files: list|null         (meta)
//           - trackers: list|null      (meta)
//           - webseeds: list|null      (meta)
//           - magnet: string|null      (built by public/index.php)
//   $show_meta: when false (default) the four meta elements are omitted.
//               When true, non-null meta values are appended inside each
//               <torrent> element, all strings routed through xml_escape().
//               The <magnet> element is emitted either way when non-null.
//
// Returns: XML string.

/**
 * @param list<array{
 *     info_hash: string|null,
 *     name: string|null,
 *     size: int,
 *     downloads: int,
 *     seeders: int,
 *     leechers: int,
 *     peers: int,
 *     traffic: int,
 *     filename?: string|null,
 *     files?: list<array{path: string, length: int}>|null,
 *     trackers?: list<string>|null,
 *     webseeds?: list<string>|null,
 *     magnet?: string|null,
 * }> $index
 */
function view_index_xml(array $index, bool $show_meta = false): string
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
            '<traffic>'.$torrent['traffic'].'</traffic>';

        ////	magnet
        // Built by public/index.php; emitted in both modes when non-null.
        // xml_escape matters here — magnet URIs join parameters with '&'.
        $magnet = $torrent['magnet'] ?? null;
        if ($magnet !== null) {
            $xml .= '<magnet>'.xml_escape($magnet).'</magnet>';
        }

        if ($show_meta) {
            ////	filename
            // Emit only when the value is non-null.
            $filename = $torrent['filename'] ?? null;
            if ($filename !== null) {
                $xml .= '<filename>'.xml_escape($filename).'</filename>';
            }

            ////	files
            // Emit a <files> block with one <file> per entry when non-null.
            $files = $torrent['files'] ?? null;
            if ($files !== null) {
                $xml .= '<files>';
                foreach ($files as $file) {
                    $xml .= '<file>'.
                        '<path>'.xml_escape($file['path']).'</path>'.
                        '<length>'.$file['length'].'</length>'.
                        '</file>';
                }
                $xml .= '</files>';
            }

            ////	trackers
            // Emit a <trackers> block with one <tracker> per URL when non-null.
            $trackers = $torrent['trackers'] ?? null;
            if ($trackers !== null) {
                $xml .= '<trackers>';
                foreach ($trackers as $tracker) {
                    $xml .= '<tracker>'.xml_escape($tracker).'</tracker>';
                }
                $xml .= '</trackers>';
            }

            ////	webseeds
            // Emit a <webseeds> block with one <webseed> per URL when non-null.
            $webseeds = $torrent['webseeds'] ?? null;
            if ($webseeds !== null) {
                $xml .= '<webseeds>';
                foreach ($webseeds as $webseed) {
                    $xml .= '<webseed>'.xml_escape($webseed).'</webseed>';
                }
                $xml .= '</webseeds>';
            }
        }

        $xml .= '</torrent>';
    }

    return $xml.'</torrents>';
}
