<?php

require_once __DIR__.'/../_phoenix.php';
$failure = false;

$tests = glob(__DIR__.'/phoenix/*.php');
foreach ( $tests as $test) {
	echo 'Starting Test: '.$test.PHP_EOL;
	require_once $test;
	echo 'Ending Test: '.$test.PHP_EOL;
}

if ( $failure ) {
	exit(1);
}
