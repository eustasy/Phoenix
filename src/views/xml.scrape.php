<?php

declare(strict_types=1);

////	view_scrape_xml
// Renders a normalized $scrape array as XML scrape response.
// Caller is responsible for emitting the Content-Type header.
//
// Arguments:
//   $scrape: array of torrents indexed by info_hash (40-char hex), each with:
//            - info_hash: string (40-char hex)
//            - seeders: int
//            - leechers: int
//            - peers: int
//            - size: int
//            - downloads: int
//            - traffic: int
//
// Returns: XML string.
/**
 * @param array<string, array{info_hash: string, seeders: int, leechers: int, peers: int, size: int, downloads: int, traffic: int}> $scrape
 * @param int $min_request_interval BEP 48 scrape-throttle hint (seconds); 0 omits it
 */
function view_scrape_xml(array $scrape, int $min_request_interval = 0): string
{
    // Wrapped in a <scrape> root so the document is well-formed even when
    // $scrape contains zero or many torrents — a bare list of <torrent>
    // siblings has no root element and isn't valid XML.
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><scrape>';
    foreach ($scrape as $torrent) {
        $xml .= '<torrent>'.
            '<info_hash>'.$torrent['info_hash'].'</info_hash>'.
            '<seeders>'  .$torrent['seeders']  .'</seeders>'.
            '<leechers>' .$torrent['leechers'] .'</leechers>'.
            '<peers>'    .$torrent['peers']    .'</peers>'.
            '<size>'     .$torrent['size']     .'</size>'.
            '<downloads>'.$torrent['downloads'].'</downloads>'.
            '<traffic>'  .$torrent['traffic']  .'</traffic>'.
        '</torrent>';
    }

    // BEP 48's min_request_interval (parity with the bencode `flags` dict).
    if ($min_request_interval > 0) {
        $xml .= '<min_request_interval>'.$min_request_interval.'</min_request_interval>';
    }

    return $xml.'</scrape>';
}
