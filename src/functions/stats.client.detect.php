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

    // Azureus-style two-letter client codes ('-XX####-'), from BEP 20 and the
    // BitTorrentSpecification registry. An unrecognised code falls through to
    // the literal code below, which is better than guessing: a wrong name is
    // worse than a bare code, because it is believed.
    //
    // Names match the registry exactly so that labels this tracker writes merge
    // with ones written by other implementations of the same table — "DE" is
    // "DelugeTorrent" and "UM" is "µTorrent for Mac" for that reason, not
    // because those read better.
    static $azureus = [
        '7T' => 'aTorrent for Android',
        'AB' => 'AnyEvent::BitTorrent',
        'AG' => 'Ares',
        'A~' => 'Ares',
        'AR' => 'Arctic',
        'AT' => 'Artemis',
        'AV' => 'Avicora',
        'AX' => 'BitPump',
        'AZ' => 'Azureus',
        'BB' => 'BitBuddy',
        'BC' => 'BitComet',
        'BE' => 'Baretorrent',
        'BF' => 'Bitflu',
        'BG' => 'BTG',
        'BI' => 'BiglyBT',
        'BL' => 'BitBlinder',
        'BP' => 'BitTorrent Pro',
        'BR' => 'BitRocket',
        'BS' => 'BTSlave',
        'BT' => 'BitTorrent',
        'BW' => 'BitWombat',
        'BX' => 'Bittorrent X',
        'CD' => 'Enhanced CTorrent',
        'CT' => 'CTorrent',
        'DE' => 'DelugeTorrent',
        'DP' => 'Propagate Data Client',
        'EB' => 'EBit',
        'ES' => 'electric sheep',
        'FC' => 'FileCroc',
        'FD' => 'Free Download Manager',
        'FT' => 'FoxTorrent',
        'FX' => 'Freebox BitTorrent',
        'GS' => 'GSTorrent',
        'HK' => 'Hekate',
        'HL' => 'Halite',
        'HM' => 'hMule',
        'HN' => 'Hydranode',
        'IL' => 'iLivid',
        'JS' => 'Justseed.it client',
        'JT' => 'JavaTorrent',
        'KG' => 'KGet',
        'KT' => 'KTorrent',
        'LC' => 'LeechCraft',
        'LH' => 'LH-ABC',
        'LP' => 'Lphant',
        'LT' => 'libtorrent',
        'lt' => 'libTorrent',
        'LW' => 'LimeWire',
        'MK' => 'Meerkat',
        'MO' => 'MonoTorrent',
        'MP' => 'MooPolice',
        'MR' => 'Miro',
        'MT' => 'MoonlightTorrent',
        'NB' => 'Net::BitTorrent',
        'NX' => 'Net Transport',
        'OS' => 'OneSwarm',
        'OT' => 'OmegaTorrent',
        'PB' => 'Protocol::BitTorrent',
        'PD' => 'Pando',
        'PI' => 'PicoTorrent',
        'PT' => 'PHPTracker',
        'qB' => 'qBittorrent',
        'QD' => 'QQDownload',
        'QT' => 'Qt 4 Torrent example',
        'RT' => 'Retriever',
        'RZ' => 'RezTorrent',
        'S~' => 'Shareaza',
        'SB' => 'Swiftbit',
        'SD' => 'Thunder',
        'SM' => 'SoMud',
        'SP' => 'BitSpirit',
        'SS' => 'SwarmScope',
        'ST' => 'SymTorrent',
        'st' => 'sharktorrent',
        'SZ' => 'Shareaza',
        'TB' => 'Torch',
        'TE' => 'terasaur Seed Bank',
        'TL' => 'Tribler',
        'TN' => 'TorrentDotNET',
        'TR' => 'Transmission',
        'TS' => 'Torrentstorm',
        'TT' => 'TuoTu',
        'UL' => 'uLeecher!',
        'UM' => 'µTorrent for Mac',
        'UT' => 'µTorrent',
        'UW' => 'µTorrent Web',
        'VG' => 'Vagaa',
        'WD' => 'WebTorrent Desktop',
        'WT' => 'BitLet',
        'WW' => 'WebTorrent',
        'WY' => 'FireTorrent',
        'XF' => 'Xfplay',
        'XL' => 'Xunlei',
        'XS' => 'XSwifter',
        'XT' => 'XanTorrent',
        'XX' => 'Xtorrent',
        'ZT' => 'ZipTorrent',
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
