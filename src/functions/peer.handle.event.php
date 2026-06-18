<?php

declare(strict_types=1);

////	peer_handle_event
// Applies an announce's event to the peer's stored row and fires the matching
// lifecycle hooks, returning whether the caller should go on to build a
// peer-list response. Extracted from announce_controller() so the controller
// stays thin and the event / idempotency logic is unit-testable on its own.
//
// $peer is taken by reference and mutated: $peer['old'] is set to the current
// stored row (or false), and a 'completed' event forces $peer['state'] = 1.
//
// Events:
//   stopped   — delete the peer (only when one exists, so a duplicate 'stopped'
//               does not re-fire the hook) and return false: the client expects
//               an empty body.
//   completed — count the download + fire download.complete, but only on the
//               leech -> seed transition; then fall through to new/change/access.
//   (other)   — a new or changed peer is REPLACE'd (peer.new / peer.change),
//               an unchanged one just has its timestamp bumped (peer.access).
//
// Returns false for 'stopped' (no response body), true otherwise.
/**
 * @param PhoenixSettings $settings
 * @param array<string, mixed> $peer
 */
function peer_handle_event(mysqli $connection, array $settings, int $time, array &$peer, ?string $event): bool
{
    require_once __DIR__.'/../model/peer.select.php';
    require_once __DIR__.'/peer.changed.php';
    require_once __DIR__.'/phoenix.hook.php';

    $peer['old'] = peer_select($connection, $settings, $peer);

    // EVENT: stopped — remove the peer; the client expects no body. Only act
    // when there is actually a peer to remove: clients such as Transmission send
    // every announce twice, and a duplicate 'stopped' would otherwise re-fire
    // the hook on a no-op delete (the row is already gone, $peer['old'] false).
    if ($event === 'stopped') {
        if ($peer['old']) {
            require_once __DIR__.'/../model/peer.delete.php';
            peer_delete($connection, $settings, $peer);
            phoenix_hook('peer.stopped', $connection, $settings, $time, $peer);
        }

        return false;
    }

    // EVENT: completed — count the download and force seeding state, but only on
    // the leech -> seed transition. A peer already recorded as seeding (state 1)
    // re-announcing 'completed' — including the duplicate announces Transmission
    // and others send — must not increment the counter or re-fire the hook.
    // (Sequential duplicates are fully deduped here; truly concurrent workers
    // would need an atomic conditional UPDATE for the same guarantee.)
    if ($event === 'completed') {
        $already_seeding = $peer['old'] && (int) ($peer['old']['state'] ?? 0) === 1;
        $peer['state'] = 1;
        if (! $already_seeding) {
            require_once __DIR__.'/../model/torrent.increment.downloads.php';
            torrent_increment_downloads($connection, $settings, is_string($peer['info_hash']) ? $peer['info_hash'] : '');
            phoenix_hook('download.complete', $connection, $settings, $time, $peer);
        }
    }

    // CHANGED or NEW peer — REPLACE the row, then run the new/change hook.
    if (peer_changed($peer, $peer['old'])) {
        require_once __DIR__.'/../model/peer.insert.php';
        peer_insert($connection, $settings, $time, $peer);
        phoenix_hook($peer['old'] ? 'peer.change' : 'peer.new', $connection, $settings, $time, $peer);

        // UNCHANGED peer — bump the access timestamp only.
    } else {
        require_once __DIR__.'/../model/peer.update.php';
        peer_update($connection, $settings, $time, $peer);
        phoenix_hook('peer.access', $connection, $settings, $time, $peer);
    }

    return true;
}
