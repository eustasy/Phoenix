<?php

declare(strict_types=1);

////	peer_parse_announce_optional
// Sanitises the optional announce parameters from $_GET, applying tracker
// defaults from $settings where the request is silent. Returns an
// associative array intended to be merged into $peer.
function peer_parse_announce_optional(array $get, array $settings): array {

	// left / state — tri-state.
	// missing  → left=-1, state=0 (unknown/leeching)
	// zero     → left=0,  state=1 (seeding)
	// positive → left=N,  state=0 (leeching)
	if ( !isset($get['left']) ) {
		$left  = -1;
		$state = 0;
	} else if ( intval($get['left']) === 0 ) {
		$left  = 0;
		$state = 1;
	} else {
		$left  = intval($get['left']);
		$state = 0;
	}

	// compact (BEP 23) — explicit request flag wins, otherwise tracker default.
	if ( isset($get['compact']) ) {
		$compact = intval($get['compact']) > 0 ? 1 : 0;
	} else {
		$compact = $settings['default_compact'] ? 1 : 0;
	}

	// no_peer_id — opt-in via the request only; default off.
	$no_peer_id = (isset($get['no_peer_id']) && intval($get['no_peer_id']) > 0) ? 1 : 0;

	// uploaded / downloaded — non-negative integer or 0.
	$uploaded   = (isset($get['uploaded'])   && intval($get['uploaded'])   >= 0) ? intval($get['uploaded'])   : 0;
	$downloaded = (isset($get['downloaded']) && intval($get['downloaded']) >= 0) ? intval($get['downloaded']) : 0;

	// numwant — default_peers when missing; otherwise clamp to [1, max_peers].
	if ( !isset($get['numwant']) ) {
		$numwant = $settings['default_peers'];
	} else {
		$numwant = intval($get['numwant']);
		if ( $numwant > $settings['max_peers'] || $numwant < 1 ) {
			$numwant = $settings['max_peers'];
		}
	}

	return array(
		'left'       => $left,
		'state'      => $state,
		'compact'    => $compact,
		'no_peer_id' => $no_peer_id,
		'uploaded'   => $uploaded,
		'downloaded' => $downloaded,
		'numwant'    => $numwant,
	);
}
