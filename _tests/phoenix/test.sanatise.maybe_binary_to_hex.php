<?php

$failure = false;
require_once $settings['functions'].'function.sanitise.maybe_binary_to_hex.php';

// 20 binary gives 40 hex
$binary = 'whyonearthwouldidoth';
$result = maybe_binary_to_hex($binary);
$length = strlen($result);
if ( $length != 40 ) {
	echo 'String length is wrong for test 1, it is '.$length;
	$failure = true;
}

// 40 hex gives sanatised 40 hex
$binary = '7768796f6e6561727468776f756c6469646f7468';
$result = maybe_binary_to_hex($binary);
$length = strlen($result);
if ( $length != 40 ) {
	echo 'String length is wrong for test 2, it is '.$length;
	$failure = true;
}

// other length gives "false"
$binary = '!"£$%^&*()_+-={}[]:@~;\'#';
$result = maybe_binary_to_hex($binary);
if ( $result ) {
	echo 'Positive result returned for test 3.';
	$failure = true;
}


if ( $failure ) {
	exit(1);
}
