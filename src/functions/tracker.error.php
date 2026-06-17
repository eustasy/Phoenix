<?php

declare(strict_types=1);

////	Fatal Error
// Exits with a tracker-format error in whichever serialisation the caller
// asked for (?xml, ?json, otherwise bencode). $retry_in is BEP 31's optional
// hint: an integer number of seconds the client should wait before retrying,
// or the string "never" for a rejection that won't ever succeed (e.g. a
// disallowed torrent). Left null, no hint is emitted.
//
// Views are required relative to __DIR__ so this file works the same whether
// it's loaded from phoenix.php's file scope or from a function body.

function tracker_error(string $error, int|string|null $retry_in = null): never
{
    if (isset($_GET['xml'])) {
        require_once __DIR__.'/../views/xml.error.php';
        header('Content-Type: text/xml');
        echo view_error_xml($error, $retry_in);
    } elseif (isset($_GET['json'])) {
        require_once __DIR__.'/../views/json.error.php';
        header('Content-Type: application/json');
        echo view_error_json($error, $retry_in);
    } else {
        require_once __DIR__.'/../views/bencode.error.php';
        header('Content-Type: text/plain; charset=ISO-8859-1');
        echo view_error_bencode($error, $retry_in);
    }
    exit(2);
}
