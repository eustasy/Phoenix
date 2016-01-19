<?php

require_once $settings['functions'].'function.peer.completed.php';

$peer['info_hash'] = '__TEST_1__';

$result = peer_completed($connection, $settings, $peer);

if ( !$result ) {
	echo 'Function did not execute successfully.'.PHP_EOL;
	exit(1);
}
