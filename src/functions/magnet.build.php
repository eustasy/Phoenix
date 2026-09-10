<?php

declare(strict_types=1);

////	magnet_build
// Assembles a magnet URI (BEP 9) from a normalized torrent row:
// xt=urn:btih:<info_hash>, dn=<name>, xl=<size>, tr=<announce_url> then the
// stored trackers, ws=<webseeds> (BEP 19). The stored tracker/webseed meta
// is embedded only when $include_meta is set, mirroring the index_show_meta
// gating in the index views so the magnet can't bypass the meta gate.
// Returns null when the row carries no info_hash.

/** @param array{info_hash: string|null, name: string|null, size: int, filename?: string|null, trackers?: list<string>|null, webseeds?: list<string>|null} $torrent */
function magnet_build(array $torrent, string $announce_url, bool $include_meta = false): ?string
{
    if (empty($torrent['info_hash'])) {
        return null;
    }

    $magnet = 'magnet:?xt=urn:btih:'.$torrent['info_hash'];

    // dn is the name a client shows — and often the name it saves under — before
    // any metadata arrives, so the torrent's own filename beats the registry
    // title when it is available ("…-amd64.20260219.iso" over "elementary OS
    // Circe"). filename is gated meta, so fall back to the title when it is not
    // being published.
    $display = $include_meta && ! empty($torrent['filename'])
        ? $torrent['filename']
        : ($torrent['name'] ?? '');
    if ($display !== '') {
        $magnet .= '&dn='.rawurlencode($display);
    }
    if ($torrent['size'] > 0) {
        $magnet .= '&xl='.$torrent['size'];
    }

    // The tracker's own announce URL leads the tier list; stored trackers
    // follow, deduped in case the operator's URL is among them.
    $trackers = $announce_url !== '' ? [$announce_url] : [];
    if ($include_meta && ! empty($torrent['trackers'])) {
        $trackers = array_merge($trackers, $torrent['trackers']);
    }
    foreach (array_values(array_unique($trackers)) as $tracker) {
        $magnet .= '&tr='.rawurlencode($tracker);
    }

    // Deduped like the trackers above — a repeated ws is wasted URI length and
    // some clients will queue the same source twice.
    if ($include_meta && ! empty($torrent['webseeds'])) {
        foreach (array_values(array_unique($torrent['webseeds'])) as $webseed) {
            $magnet .= '&ws='.rawurlencode($webseed);
        }
    }

    return $magnet;
}
