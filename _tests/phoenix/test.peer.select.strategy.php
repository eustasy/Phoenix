<?php

require_once $settings['functions'].'function.peer.select.strategy.php';

$default_settings = array('random_peers' => true, 'random_limit' => 500);

// Case 1: Completed (left == 0): filter to leechers, nearest-to-done first.
$peer = array('left' => 0, 'downloaded' => 100);
$r = peer_select_strategy($peer, 5, 5, $default_settings);
if ( strpos($r['where'], "`state`='0'") === false ) {
	echo 'Error: completed peer should filter to leechers only.'.PHP_EOL;
	$failure = true;
}
if ( $r['order'] !== ' ORDER BY `left` ASC, `updated` DESC' ) {
	echo 'Error: completed peer should order by left ASC, updated DESC.'.PHP_EOL;
	$failure = true;
}

// Case 2: Just started (left > downloaded): filter to seeders + likely-seeders.
$peer = array('left' => 1000, 'downloaded' => 100);
$r = peer_select_strategy($peer, 5, 5, $default_settings);
if ( strpos($r['where'], "`state`='1' OR") === false ) {
	echo 'Error: just-started peer should filter to seeders + likely-seeders.'.PHP_EOL;
	$failure = true;
}
if ( $r['order'] !== ' ORDER BY `updated` DESC' ) {
	echo 'Error: just-started peer should order by updated DESC.'.PHP_EOL;
	$failure = true;
}

// Case 3a: In-progress, small swarm: no RAND().
$peer = array('left' => 100, 'downloaded' => 1000);
$r = peer_select_strategy($peer, 10, 10, $default_settings);
if ( strpos($r['order'], 'RAND()') !== false ) {
	echo 'Error: small swarm should not use RAND().'.PHP_EOL;
	$failure = true;
}

// Case 3b: In-progress, large swarm: RAND().
$peer = array('left' => 100, 'downloaded' => 1000);
$r = peer_select_strategy($peer, 300, 300, $default_settings);
if ( strpos($r['order'], 'RAND()') === false ) {
	echo 'Error: large swarm should use RAND().'.PHP_EOL;
	$failure = true;
}

// Case 3c: random_peers disabled, large swarm: still no RAND().
$peer = array('left' => 100, 'downloaded' => 1000);
$r = peer_select_strategy($peer, 1000, 1000, array('random_peers' => false, 'random_limit' => 500));
if ( strpos($r['order'], 'RAND()') !== false ) {
	echo 'Error: random_peers=false should never use RAND().'.PHP_EOL;
	$failure = true;
}

// Case 4: State unknown (left < 0).
$peer = array('left' => -1, 'downloaded' => 0);
$r = peer_select_strategy($peer, 5, 5, $default_settings);
if ( $r['order'] !== ' ORDER BY `updated` DESC' ) {
	echo 'Error: unknown state should order by updated DESC.'.PHP_EOL;
	$failure = true;
}
if ( $r['where'] !== '' ) {
	echo 'Error: unknown state should add no WHERE filter.'.PHP_EOL;
	$failure = true;
}
