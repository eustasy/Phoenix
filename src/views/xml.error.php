<?php

declare(strict_types=1);

////	view_error_xml
// Renders a tracker error as XML. Returns the XML string but does NOT exit —
// caller is responsible for echoing and terminating the script.
function view_error_xml(string $error): string
{
    require_once __DIR__.'/../functions/xml.escape.php';

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
        '<error>'.xml_escape($error).'</error>';
}
