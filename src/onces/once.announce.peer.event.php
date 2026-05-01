<?php

declare(strict_types=1);

require_once $settings['model'].'peer.select.php';
require_once $settings['functions'].'function.peer.changed.php';
require_once $settings['functions'].'function.phoenix.hook.php';

$peer['old'] = peer_select($connection, $settings, $peer);

$event = $_GET['event'] ?? null;

// EVENT: stopped — remove the peer and exit; the client expects no body.
if ( $event === 'stopped' ) {
	require_once $settings['functions'].'function.peer.delete.php';
	peer_delete($connection, $settings, $peer);
	phoenix_hook('peer.stopped', $connection, $settings, $time, $peer);
	exit;
}

// EVENT: completed — increment downloads and force seeding state.
if ( $event === 'completed' ) {
	$peer['state'] = 1;
	require_once $settings['functions'].'function.peer.completed.php';
	peer_completed($connection, $settings, $peer);
	phoenix_hook('download.complete', $connection, $settings, $time, $peer);
}

// CHANGED or NEW peer — REPLACE the row, then run new/change hook.
if ( peer_changed($peer, $peer['old']) ) {
	require_once $settings['functions'].'function.peer.new.php';
	peer_new($connection, $settings, $time, $peer);
	phoenix_hook($peer['old'] ? 'peer.change' : 'peer.new', $connection, $settings, $time, $peer);

// UNCHANGED peer — bump the access timestamp only.
} else {
	require_once $settings['functions'].'function.peer.access.php';
	peer_access($connection, $settings, $time, $peer);
	phoenix_hook('peer.access', $connection, $settings, $time, $peer);
}
