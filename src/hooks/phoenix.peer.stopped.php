<?php

declare(strict_types=1);

////	Peer Stopped
// The peer sent a "stopped" signal; it has been deleted from the index.
//
// Logs a 'stopped' event via stats_log_event() — a no-op unless stats are
// enabled and 'stopped' is opted into stats_events. The shared logger keeps the
// privacy contract: peer_id and IP are used transiently (client label + coarse
// geo) and never stored.
//
// Runs inside phoenix_hook()'s scope, so $connection, $settings, $time, and
// $peer are already in scope. Hooks fire per event and must declare no
// functions of their own (FPM workers include them many times per process).

/**
 * @var mysqli $connection
 * @var PhoenixSettings $settings
 * @var int $time
 * @var PhoenixPeer $peer
 */

require_once __DIR__.'/../functions/stats.log.event.php';
stats_log_event($connection, $settings, $time, $peer, 'stopped');
