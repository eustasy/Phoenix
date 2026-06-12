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
//
// Returns: XML string.
/** @param array{user: string, info_hash: string, name: string|null, size: int, listed: int} $torrent */
function view_torrent_add_xml(array $torrent): string
{
    require_once __DIR__.'/../functions/xml.escape.php';

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
        '<torrent>'.
        '<user>'.xml_escape($torrent['user']).'</user>'.
        '<info_hash>'.$torrent['info_hash'].'</info_hash>'.
        '<name>'.xml_escape($torrent['name'] ?? '').'</name>'.
        '<size>'.$torrent['size'].'</size>'.
        '<listed>'.$torrent['listed'].'</listed>'.
        '</torrent>';
}
