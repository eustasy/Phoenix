<?php

declare(strict_types=1);

////	peer_format_bencode
// Formats a single row from the peers table as a bencode dictionary entry,
// per BEP 3 announce response (non-compact mode). $include_peer_id should be
// the negation of $peer['no_peer_id'] — peers that explicitly request omission
// should pass false.
function peer_format_bencode(array $row, bool $include_peer_id): string {
	$out = '';
	if ( $row['ipv4'] != null ) {
		$out .= 'd2:ip'.strlen($row['ipv4']).':'.$row['ipv4'].
			'4:porti'.$row['portv4'].'e';
	} else if ( $row['ipv6'] != null ) {
		$out .= 'd2:ip'.strlen($row['ipv6']).':'.$row['ipv6'].
			'4:porti'.$row['portv6'].'e';
	}
	if ( $include_peer_id ) {
		$out .= '7:peer id20:'.hex2bin($row['peer_id']);
	}
	$out .= 'e';
	return $out;
}
