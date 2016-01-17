<?php

require_once __DIR__.'/../_phoenix.php';

foreach ( glob(__DIR__.'/phoenix/*.php') as $test) {
	echo 'Starting Test: '.$test;
	require_once $test;
	echo 'Ending Test: '.$test;
}
