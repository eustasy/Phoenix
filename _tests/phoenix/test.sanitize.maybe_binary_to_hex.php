<?php

require_once $settings['functions'].'function.sanitize.maybe_binary_to_hex.php';

// 20 binary gives 40 hex
$binary = 'whyonearthwouldidoth';
$result = maybe_binary_to_hex($binary);
$length = strlen($result);
if ( $length != 40 ) {
	echo 'String length is wrong for test 1, it is '.$length.PHP_EOL;
	$failure = true;
}

// 40 hex gives sanitized 40 hex
$binary = '7768796f6e6561727468776f756c6469646f7468';
$result = maybe_binary_to_hex($binary);
$length = strlen($result);
if ( $length != 40 ) {
	echo 'String length is wrong for test 2, it is '.$length.PHP_EOL;
	$failure = true;
}

// other length gives "false"
$binary = '!"£$%^&*()_+-={}[]:@~;\'#';
$result = maybe_binary_to_hex($binary);
if ( $result ) {
	echo 'Positive result returned for test 3.'.PHP_EOL;
	$failure = true;
}

// variables may be url encoded
// should return string(40) "fce720af722a813a184c5550a924aaa60a8d9af1"
$binary = '%fc%e7%20%afr%2a%81%3a%18LUP%a9%24%aa%a6%0a%8d%9a%f1';
$result = maybe_binary_to_hex($binary);
var_dump($result);
if ( !$result ) {
	echo 'Positive result returned for test 4.'.PHP_EOL;
	$failure = true;
}
