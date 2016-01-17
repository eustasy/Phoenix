<?php

require_once __DIR__.'/../_phoenix.php';

foreach ( glob(__DIR__.'/phoenix/*.php') as $test) {
	require_once $test;
}
