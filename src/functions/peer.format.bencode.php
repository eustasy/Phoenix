<?php

declare(strict_types=1);

////	peer_format_bencode
// Formats a single row from the peers table as a bencode dictionary entry,
// per BEP 3 announce response (non-compact mode). $include_peer_id should be
// the negation of $peer['no_peer_id'] — peers that explicitly request omission
// should pass false.
//
// Returns '' when the row has neither an IPv4 nor an IPv6 address, so the
// caller can safely concatenate without producing a stray closing 'e'.
//
// BEP 3 requires dict keys in sorted byte order: 'ip' < 'peer id' < 'port'.
function peer_format_bencode(array $row, bool $include_peer_id): string {
	if ( $row['ipv4'] != null ) {
		$ip   = $row['ipv4'];
		$port = $row['portv4'];
	} else if ( $row['ipv6'] != null ) {
		$ip   = $row['ipv6'];
		$port = $row['portv6'];
	} else {
		return '';
	}

	$out = 'd2:ip'.strlen($ip).':'.$ip;
	if ( $include_peer_id ) {
		$out .= '7:peer id20:'.hex2bin($row['peer_id']);
	}
	$out .= '4:porti'.$port.'e';
	return $out.'e';
}
