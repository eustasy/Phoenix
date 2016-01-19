<?php

require_once $settings['functions'].'function.peer.new.php';

$peer['info_hash'] = '__TEST_1__';
$peer['peer_id'] = '__TEST_1__';
$peer['state'] = '__TEST_1__';
$peer['left'] = 0;
$peer['ipv4'] = false;
$peer['ipv6'] = false;
$peer['port'] = false;
$peer['portv4'] = false;
$peer['portv6'] = false;

$result = peer_new($connection, $settings, $time, $peer);
if ( !$result ) {
	echo 'Error: Did not create peer.'.PHP_EOL;
	$failure = true;
}

$result = peer_new($connection, $settings, $time, $peer);
if ( !$result ) {
	echo 'Error: Did not replace peer.'.PHP_EOL;
	$failure = true;
}
