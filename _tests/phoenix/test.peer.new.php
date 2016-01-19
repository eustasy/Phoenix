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

peer_new($connection, $settings, $time, $peer);
