<?php

require_once __DIR__.'/../_phoenix.php';

$tests = glob(__DIR__.'/phoenix/*.php');
foreach ( $tests as $test) {
	echo 'Starting Test: '.$test;
	require_once $test;
	echo 'Ending Test: '.$test;
}
var_dump($tests);
