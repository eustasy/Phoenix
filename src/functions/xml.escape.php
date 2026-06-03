<?php

declare(strict_types=1);

////	xml_escape
// Escape a text value for safe inclusion in XML — both element content and
// quoted attribute values (ENT_QUOTES covers " and '). This is the single
// place Phoenix's XML views route free text through, mirroring bencode_encode()
// as the one emitter for bencode output. Hex (info_hash/peer_id) and integer
// fields don't need it, but routing all *text* through here means a view can't
// silently emit an unescaped string.
function xml_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}
