<?php

declare(strict_types=1);

////	stats_client_detect
// Maps a BitTorrent peer_id to a coarse client label (e.g. 'qBittorrent
// 4.6.2.0'). Pure and table-driven: the peer_id is used only to derive the
// label and is NEVER stored — that privacy contract is the whole point of
// keeping this in a function the hooks call transiently.
//
// peer_id reaches us as a 40-char lowercase HEX string (see
// sanitize.maybe_binary_to_hex.php), so we hex2bin() back to the raw 20 bytes
// before pattern-matching against the BEP 20 conventions. Anything that does
// not decode to a recognised prefix collapses to 'Unknown'. The result always
// fits the events.client varchar(64) column.
function stats_client_detect(string $peer_id): string
{
    require_once __DIR__.'/stats.client.version.php';

    // Azureus-style two-letter client codes ('-XX####-'). Extend as needed;
    // an unrecognised code falls through to the literal code below.
    static $azureus = [
        'AZ' => 'Azureus',
        'BC' => 'BitComet',
        'BW' => 'BiglyBT',
        'DE' => 'Deluge',
        'FD' => 'Free Download Manager',
        'LT' => 'libtorrent',
        'lt' => 'libTorrent',
        'PI' => 'PicoTorrent',
        'qB' => 'qBittorrent',
        'RT' => 'rTorrent',
        'TL' => 'Tribler',
        'TR' => 'Transmission',
        'UM' => 'µTorrent Mac',
        'UT' => 'µTorrent',
        'UW' => 'µTorrent Web',
        'WW' => 'WebTorrent',
    ];

    // Shadow's-style single-letter client codes (one letter then version chars,
    // e.g. 'T03I-' for BitTornado).
    static $shadows = [
        'A' => 'ABC',
        'O' => 'Osprey Permaseed',
        'Q' => 'BTQueue',
        'R' => 'Tribler',
        'S' => "Shadow's",
        'T' => 'BitTornado',
        'U' => 'UPnP NAT Bit Torrent',
    ];

    // hex2bin() warns (and returns false) on odd-length or non-hex input; the
    // strict guard keeps detection silent and yields 'Unknown' for garbage.
    if (strlen($peer_id) !== 40 || ! ctype_xdigit($peer_id)) {
        return 'Unknown';
    }
    $raw = hex2bin($peer_id);
    if ($raw === false || strlen($raw) !== 20) {
        return 'Unknown';
    }

    ////	Azureus-style: '-XX####-...'
    if ($raw[0] === '-' && $raw[7] === '-') {
        $code = substr($raw, 1, 2);
        // A recognised code maps to a friendly name; an unrecognised but plain
        // alphanumeric code (a real client simply missing from the table) is
        // shown as-is. Anything else is a malformed/spoofed peer_id — the two
        // code bytes are raw and may be non-printable, non-UTF-8, or HTML
        // metacharacters — so collapse to 'Unknown' rather than surface those
        // bytes into the admin views or the stored events.client label.
        if (isset($azureus[$code])) {
            $name = $azureus[$code];
        } elseif (ctype_alnum($code)) {
            $name = $code;
        } else {
            return 'Unknown';
        }
        $version = stats_client_version(substr($raw, 3, 4));

        return $version === '' ? $name : $name.' '.$version;
    }

    ////	Shadow's-style: one letter then version chars
    if (isset($shadows[$raw[0]])) {
        return $shadows[$raw[0]];
    }

    return 'Unknown';
}
