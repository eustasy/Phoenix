<?php

require_once $settings['functions'].'function.peer.format.bencode.php';

// IPv4 with peer_id
$row = array(
	'ipv4'    => '1.2.3.4',
	'ipv6'    => null,
	'portv4'  => 12345,
	'portv6'  => 0,
	'peer_id' => str_repeat('00', 20),
);
$out = peer_format_bencode($row, true);
$expected = 'd2:ip7:1.2.3.44:porti12345e7:peer id20:'.hex2bin(str_repeat('00', 20)).'e';
if ( $out !== $expected ) {
	echo 'Error: peer_format_bencode IPv4 with peer_id mismatch.'.PHP_EOL;
	echo '  Expected (hex): '.bin2hex($expected).PHP_EOL;
	echo '  Got      (hex): '.bin2hex($out).PHP_EOL;
	$failure = true;
}

// IPv4 without peer_id
$out = peer_format_bencode($row, false);
$expected = 'd2:ip7:1.2.3.44:porti12345ee';
if ( $out !== $expected ) {
	echo 'Error: peer_format_bencode IPv4 without peer_id mismatch.'.PHP_EOL;
	echo '  Expected: '.$expected.PHP_EOL;
	echo '  Got:      '.$out.PHP_EOL;
	$failure = true;
}

// IPv6 only
$row = array(
	'ipv4'    => null,
	'ipv6'    => 'dead::1',
	'portv4'  => 0,
	'portv6'  => 12345,
	'peer_id' => str_repeat('ff', 20),
);
$out = peer_format_bencode($row, false);
$expected = 'd2:ip7:dead::14:porti12345ee';
if ( $out !== $expected ) {
	echo 'Error: peer_format_bencode IPv6 only mismatch.'.PHP_EOL;
	echo '  Expected: '.$expected.PHP_EOL;
	echo '  Got:      '.$out.PHP_EOL;
	$failure = true;
}

// IPv4 takes precedence when both are set
$row = array(
	'ipv4'    => '1.2.3.4',
	'ipv6'    => 'dead::1',
	'portv4'  => 12345,
	'portv6'  => 54321,
	'peer_id' => str_repeat('00', 20),
);
$out = peer_format_bencode($row, false);
$expected = 'd2:ip7:1.2.3.44:porti12345ee';
if ( $out !== $expected ) {
	echo 'Error: peer_format_bencode should prefer IPv4 when both are present.'.PHP_EOL;
	$failure = true;
}
