<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use RuntimeException;

/**
 * Strict round-trip checks for every bencode emitter in the codebase.
 *
 * Past regressions in this area have been variations on the same theme:
 * stray or missing 'e' tokens around loops, dict keys emitted in the wrong
 * lexicographic order, length prefixes that don't match the byte body. The
 * surface symptom is "some clients tolerate it, strict ones don't" — easy to
 * miss without an actual decoder.
 *
 * The decoder below is intentionally strict: it rejects trailing bytes,
 * unterminated containers, and non-string dict keys, and assertSortedKeys()
 * walks the decoded tree to verify dict keys are in raw-byte sorted order.
 */
class BencodeRoundTripTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once __DIR__.'/../../src/views/bencode.error.php';
		require_once __DIR__.'/../../src/views/bencode.scrape.php';
		require_once __DIR__.'/../../src/views/bencode.announce.php';
		require_once __DIR__.'/../../src/functions/function.peer.format.bencode.php';
	}

	////	Decoder

	/**
	 * Decode one bencode value starting at $i.
	 * @return array{0: mixed, 1: int} [value, next_offset]
	 */
	private static function bdecode(string $s, int $i): array {
		if ($i >= strlen($s)) {
			throw new RuntimeException("unexpected EOF at $i");
		}
		$c = $s[$i];

		if ($c === 'i') {
			$end = strpos($s, 'e', $i + 1);
			if ($end === false) {
				throw new RuntimeException("unterminated int at $i");
			}
			$num = substr($s, $i + 1, $end - $i - 1);
			if (!preg_match('/^-?\d+$/', $num)) {
				throw new RuntimeException("malformed int '$num' at $i");
			}
			return [(int) $num, $end + 1];
		}

		if ($c === 'l') {
			$out = [];
			$i++;
			while ($i < strlen($s) && $s[$i] !== 'e') {
				[$v, $i] = self::bdecode($s, $i);
				$out[] = $v;
			}
			if ($i >= strlen($s)) {
				throw new RuntimeException('unterminated list');
			}
			return [$out, $i + 1];
		}

		if ($c === 'd') {
			$out = [];
			$keys = [];
			$i++;
			while ($i < strlen($s) && $s[$i] !== 'e') {
				[$k, $i] = self::bdecode($s, $i);
				if (!is_string($k)) {
					throw new RuntimeException("non-string dict key at $i");
				}
				[$v, $i] = self::bdecode($s, $i);
				$out[$k] = $v;
				$keys[] = $k;
			}
			if ($i >= strlen($s)) {
				throw new RuntimeException('unterminated dict');
			}
			$out['__keys__'] = $keys;
			return [$out, $i + 1];
		}

		if (ctype_digit($c)) {
			$colon = strpos($s, ':', $i);
			if ($colon === false) {
				throw new RuntimeException("string missing colon at $i");
			}
			$len = (int) substr($s, $i, $colon - $i);
			$start = $colon + 1;
			if ($start + $len > strlen($s)) {
				throw new RuntimeException("string overruns at $i");
			}
			return [substr($s, $start, $len), $start + $len];
		}

		throw new RuntimeException('unexpected byte 0x'.bin2hex($c)." at $i");
	}

	/** Decode and assert no trailing bytes. */
	private function decode(string $s) {
		[$v, $end] = self::bdecode($s, 0);
		if ($end !== strlen($s)) {
			$this->fail((strlen($s) - $end).' trailing byte(s) after decode: '.bin2hex(substr($s, $end, 16)));
		}
		return $v;
	}

	/** Recursively assert every dict's keys are in raw-byte sorted order. */
	private function assertSortedKeys($value, string $where = 'root'): void {
		if (is_array($value) && isset($value['__keys__'])) {
			$keys = $value['__keys__'];
			$sorted = $keys;
			sort($sorted, SORT_STRING);
			$this->assertSame($sorted, $keys, "dict at $where has out-of-order keys");
			foreach ($value as $k => $v) {
				if ($k === '__keys__') continue;
				$this->assertSortedKeys($v, $where.'/'.$k);
			}
		} elseif (is_array($value)) {
			foreach ($value as $i => $v) {
				$this->assertSortedKeys($v, $where.'['.$i.']');
			}
		}
	}

	////	view_error_bencode

	public function testErrorBencode(): void {
		foreach (['short message', '', 'héllo with utf-8'] as $msg) {
			$out = view_error_bencode($msg);
			$decoded = $this->decode($out);
			$this->assertSame($msg, $decoded['failure reason']);
			$this->assertSortedKeys($decoded);
		}
	}

	////	view_scrape_bencode

	public function testScrapeBencodeEmpty(): void {
		$decoded = $this->decode(view_scrape_bencode([]));
		$this->assertArrayHasKey('files', $decoded);
		$this->assertSame(['__keys__' => []], $decoded['files']);
	}

	public function testScrapeBencodeSingleAndMulti(): void {
		$entries = [
			str_repeat('a', 40) => ['info_hash' => str_repeat('a', 40), 'seeders' => 2, 'leechers' => 1, 'downloads' => 7],
			str_repeat('b', 40) => ['info_hash' => str_repeat('b', 40), 'seeders' => 0, 'leechers' => 5, 'downloads' => 0],
			str_repeat('c', 40) => ['info_hash' => str_repeat('c', 40), 'seeders' => 99, 'leechers' => 99, 'downloads' => 999],
		];

		// Single-entry case.
		$decoded = $this->decode(view_scrape_bencode([array_key_first($entries) => reset($entries)]));
		$this->assertSortedKeys($decoded);
		$key = hex2bin(str_repeat('a', 40));
		$this->assertArrayHasKey($key, $decoded['files']);
		$entry = $decoded['files'][$key];
		$this->assertSame(2, $entry['complete']);
		$this->assertSame(7, $entry['downloaded']);
		$this->assertSame(1, $entry['incomplete']);

		// Multi-entry case.
		$decoded = $this->decode(view_scrape_bencode($entries));
		$this->assertSortedKeys($decoded);
		// Three entries plus the recorded __keys__ helper.
		$this->assertCount(4, $decoded['files']);
	}

	////	peer_format_bencode

	public function testPeerFormatBencodeShape(): void {
		$row_v4 = ['ipv4' => '1.2.3.4', 'ipv6' => null, 'portv4' => 6881, 'portv6' => 0, 'peer_id' => str_repeat('aa', 20)];
		$row_v6 = ['ipv4' => null, 'ipv6' => '2001:db8::1', 'portv4' => 0, 'portv6' => 6882, 'peer_id' => str_repeat('bb', 20)];

		$decoded = $this->decode(peer_format_bencode($row_v4, true));
		$this->assertSortedKeys($decoded);
		$this->assertSame('1.2.3.4', $decoded['ip']);
		$this->assertSame(6881, $decoded['port']);
		$this->assertSame(20, strlen($decoded['peer id']));

		$decoded = $this->decode(peer_format_bencode($row_v4, false));
		$this->assertSortedKeys($decoded);
		$this->assertArrayNotHasKey('peer id', $decoded);

		$decoded = $this->decode(peer_format_bencode($row_v6, true));
		$this->assertSame('2001:db8::1', $decoded['ip']);
		$this->assertSame(6882, $decoded['port']);
	}

	public function testPeerFormatBencodeReturnsEmptyWhenNoAddress(): void {
		$row = ['ipv4' => null, 'ipv6' => null, 'portv4' => 0, 'portv6' => 0, 'peer_id' => str_repeat('00', 20)];
		// Both branches return '' so the surrounding list doesn't pick up a
		// stray closing 'e'.
		$this->assertSame('', peer_format_bencode($row, true));
		$this->assertSame('', peer_format_bencode($row, false));
	}

	////	view_announce_bencode (non-compact)

	public function testAnnounceBencodeNonCompact(): void {
		$counts = ['complete' => 5, 'incomplete' => 3];
		$cfg    = ['announce_interval' => 1800, 'min_interval' => 900];
		$row_v4 = ['ipv4' => '1.2.3.4', 'ipv6' => null, 'portv4' => 6881, 'portv6' => 0, 'peer_id' => str_repeat('aa', 20)];
		$row_v6 = ['ipv4' => null, 'ipv6' => '2001:db8::1', 'portv4' => 0, 'portv6' => 6882, 'peer_id' => str_repeat('bb', 20)];
		$row_no = ['ipv4' => null, 'ipv6' => null, 'portv4' => 0, 'portv6' => 0, 'peer_id' => str_repeat('00', 20)];

		// Empty peer list — must produce 'le' inside the response, not a stray 'e'.
		$decoded = $this->decode(view_announce_bencode($counts, $cfg, [], false, false));
		$this->assertSortedKeys($decoded);
		$this->assertSame([], array_filter($decoded['peers'], fn ($k) => $k !== '__keys__', ARRAY_FILTER_USE_KEY));

		// Address-less rows are skipped, not emitted as broken dicts.
		$decoded = $this->decode(view_announce_bencode($counts, $cfg, [$row_v4, $row_v6, $row_no], false, false));
		$this->assertSortedKeys($decoded);
		$this->assertCount(2, $decoded['peers']);

		// no_peer_id flag honored.
		$decoded = $this->decode(view_announce_bencode($counts, $cfg, [$row_v4], false, true));
		$this->assertArrayNotHasKey('peer id', $decoded['peers'][0]);
	}

	////	view_announce_bencode (compact)

	public function testAnnounceBencodeCompact(): void {
		$counts = ['complete' => 5, 'incomplete' => 3];
		$cfg    = ['announce_interval' => 1800, 'min_interval' => 900];
		$compact_v4 = ['compactv4' => 'c0a80101aabb', 'compactv6' => null];
		$compact_v6 = ['compactv4' => null,           'compactv6' => '20010db8000000000000000000000001ccdd'];
		$compact_no = ['compactv4' => null,           'compactv6' => null];

		$decoded = $this->decode(view_announce_bencode($counts, $cfg, [], true, false));
		$this->assertSortedKeys($decoded);
		$this->assertSame('', $decoded['peers']);
		$this->assertSame('', $decoded['peers6']);

		$decoded = $this->decode(view_announce_bencode($counts, $cfg, [$compact_v4, $compact_v6, $compact_no], true, false));
		$this->assertSortedKeys($decoded);
		$this->assertSame(hex2bin('c0a80101aabb'), $decoded['peers']);
		$this->assertSame(hex2bin('20010db8000000000000000000000001ccdd'), $decoded['peers6']);
	}

}
