<?php

declare(strict_types=1);

////	view_api_index_xml
// Renders the API discovery index as XML: the running Phoenix version.
// Caller is responsible for emitting the Content-Type header.
//
// Arguments:
//   $settings: config array (needs phoenix_version)
//
// Returns: XML string.

/** @param PhoenixSettings $settings */
function view_api_index_xml(array $settings): string
{
    require_once __DIR__.'/../functions/xml.escape.php';

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
        '<phoenix>'.
        '<version>'.xml_escape($settings['phoenix_version']).'</version>'.
        '</phoenix>';
}
