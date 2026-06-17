<?php

declare(strict_types=1);

////	view_error_xml
// Renders a tracker error as XML, mirroring BEP 31's 'retry in' as a <retry_in>
// child of <error> when $retry_in is given (seconds, or "never"). Nesting it
// inside <error> keeps the single root element. Returns the XML string but does
// NOT exit — caller is responsible for echoing and terminating the script.
function view_error_xml(string $error, int|string|null $retry_in = null): string
{
    require_once __DIR__.'/../functions/xml.escape.php';

    $retry = $retry_in !== null
        ? '<retry_in>'.xml_escape((string) $retry_in).'</retry_in>'
        : '';

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
        '<error>'.xml_escape($error).$retry.'</error>';
}
