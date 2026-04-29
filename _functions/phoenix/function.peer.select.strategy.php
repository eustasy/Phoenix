<?php

////	peer_select_strategy
// Determines the SQL WHERE extension and ORDER BY clause for peer selection
// based on the announcing peer's progress and the current swarm size.
// Returns array('where' => string, 'order' => string).
//
// Cases:
//  - left == 0   : announcer is seeding; only show leechers, prioritise nearest-to-done.
//  - left > downloaded : likely <50% done; only show seeders + likely-seeders, ordered by recency.
//  - left > 0 (else)   : likely >=50% done; order by progress, randomise within tiers if swarm is large.
//  - left < 0    : state unknown (left not reported); order by recency.
function peer_select_strategy($peer, $complete, $incomplete, $settings) {
	if ( $peer['left'] == 0 ) {
		return array(
			'where' => ' AND `state`=\'0\'',
			'order' => ' ORDER BY `left` ASC, `updated` DESC',
		);
	}
	if ( $peer['left'] > 0 && $peer['left'] > $peer['downloaded'] ) {
		return array(
			'where' => ' AND (`state`=\'1\' OR `downloaded` > `left`)',
			'order' => ' ORDER BY `updated` DESC',
		);
	}
	if ( $peer['left'] > 0 ) {
		$randomise = $settings['random_peers']
			&& ($complete + $incomplete) > intval($settings['random_limit']);
		return array(
			'where' => '',
			'order' => $randomise
				? ' ORDER BY `left` ASC, RAND()'
				: ' ORDER BY `left` ASC, `updated` DESC',
		);
	}
	return array(
		'where' => '',
		'order' => ' ORDER BY `updated` DESC',
	);
}
