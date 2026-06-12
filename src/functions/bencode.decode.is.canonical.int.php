<?php

declare(strict_types=1);

////	bencode_decode_is_canonical_int
// True only for a canonical bencode integer body: optional single leading '-',
// then digits, no leading zeros (except the single digit '0'), and '-0' rejected.

function bencode_decode_is_canonical_int(string $body): bool
{
    if ($body === '') {
        return false;
    }

    $digits = $body;
    $negative = false;
    if ($body[0] === '-') {
        $negative = true;
        $digits = substr($body, 1);
    }

    if ($digits === '' || ! ctype_digit($digits)) {
        return false;
    }

    // Reject leading zeros ('03', '00') and negative zero ('-0').
    if (strlen($digits) > 1 && $digits[0] === '0') {
        return false;
    }
    if ($negative && $digits === '0') {
        return false;
    }

    return true;
}
