<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class BencodeDecodeTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/bencode.encode.php';
        require_once __DIR__.'/../../src/functions/bencode.decode.php';
    }

    ////	Round-trip against the encoder

    public function testRoundTripInteger(): void
    {
        foreach ([0, 42, -7, 123456789] as $n) {
            $decoded = bencode_decode(bencode_encode($n));
            $this->assertNotFalse($decoded);
            $this->assertSame($n, $decoded['value']);
        }
    }

    public function testRoundTripString(): void
    {
        foreach (['', 'hello', 'a:b:c', "\xC3\xA9"] as $s) {
            $decoded = bencode_decode(bencode_encode($s));
            $this->assertNotFalse($decoded);
            $this->assertSame($s, $decoded['value']);
        }
    }

    public function testRoundTripBinaryString(): void
    {
        // A 20-byte hash with a leading NUL — must survive byte-for-byte.
        $raw = hex2bin('00'.str_repeat('ff', 19));
        $this->assertIsString($raw);
        $decoded = bencode_decode(bencode_encode($raw));
        $this->assertNotFalse($decoded);
        $this->assertSame($raw, $decoded['value']);
        $this->assertSame(20, strlen($decoded['value']));
    }

    public function testRoundTripList(): void
    {
        $value = [1, 2, 3, 'four', [-5, 'six']];
        $decoded = bencode_decode(bencode_encode($value));
        $this->assertNotFalse($decoded);
        $this->assertSame($value, $decoded['value']);
    }

    public function testRoundTripDict(): void
    {
        // Keys deliberately out of byte order; encoder sorts them, and the
        // decoder reads them back as an associative array.
        $value = ['peers' => 'a', 'complete' => 1, 'incomplete' => 2];
        $decoded = bencode_decode(bencode_encode($value));
        $this->assertNotFalse($decoded);
        $this->assertSame(1, $decoded['value']['complete']);
        $this->assertSame(2, $decoded['value']['incomplete']);
        $this->assertSame('a', $decoded['value']['peers']);
    }

    public function testRoundTripNestedDictAndList(): void
    {
        $value = [
            'announce' => 'http://tracker.example/announce',
            'info' => [
                'name' => 'thing',
                'length' => 12345,
                'pieces' => "\x00\x01\x02",
            ],
        ];
        $decoded = bencode_decode(bencode_encode($value));
        $this->assertNotFalse($decoded);
        $this->assertSame('thing', $decoded['value']['info']['name']);
        $this->assertSame(12345, $decoded['value']['info']['length']);
        $this->assertSame("\x00\x01\x02", $decoded['value']['info']['pieces']);
    }

    public function testEmptyListDecodes(): void
    {
        $decoded = bencode_decode('le');
        $this->assertNotFalse($decoded);
        $this->assertSame([], $decoded['value']);
    }

    public function testEmptyDictDecodes(): void
    {
        // Empty list and empty dict both collapse to [] — documented as
        // acceptable since decoding is one-way.
        $decoded = bencode_decode('de');
        $this->assertNotFalse($decoded);
        $this->assertSame([], $decoded['value']);
    }

    ////	info_raw capture

    public function testInfoRawIsNullWhenTopLevelNotDict(): void
    {
        $decoded = bencode_decode(bencode_encode([1, 2, 3]));
        $this->assertNotFalse($decoded);
        $this->assertNull($decoded['info_raw']);
    }

    public function testInfoRawIsNullWhenNoInfoKey(): void
    {
        $decoded = bencode_decode(bencode_encode(['announce' => 'http://x/']));
        $this->assertNotFalse($decoded);
        $this->assertNull($decoded['info_raw']);
    }

    public function testInfoRawMatchesEncodedInfo(): void
    {
        // The captured raw slice must hash identically to a fresh encode of just
        // the info structure — that equality is what makes the info-hash correct.
        $info = [
            'name' => 'example',
            'length' => 999,
            'piece length' => 16384,
            'pieces' => str_repeat("\xAB", 20),
        ];
        $torrent = [
            'announce' => 'http://tracker.example/announce',
            'info' => $info,
        ];
        $decoded = bencode_decode(bencode_encode($torrent));
        $this->assertNotFalse($decoded);
        $this->assertNotNull($decoded['info_raw']);
        $this->assertSame(bencode_encode($info), $decoded['info_raw']);
        $this->assertSame(sha1(bencode_encode($info)), sha1($decoded['info_raw']));
    }

    public function testInfoRawIsExactRawSliceNotReencode(): void
    {
        // Hand-build bytes where the info dict's keys are already in canonical
        // order but contain a binary value; the slice must be byte-identical.
        $infoBytes = 'd6:lengthi5e4:name3:abce';
        $data = 'd8:announce4:http4:info'.$infoBytes.'e';
        $decoded = bencode_decode($data);
        $this->assertNotFalse($decoded);
        $this->assertSame($infoBytes, $decoded['info_raw']);
    }

    public function testInfoRawCapturesTopLevelNotNestedInfo(): void
    {
        // A nested 'info' key (buried inside another value) must NOT overwrite
        // the top-level info slice — otherwise the computed info-hash would be
        // wrong. Only the top-level dict's 'info' is captured.
        $topInfo = ['length' => 99, 'name' => 'real'];
        $torrent = [
            'info' => $topInfo,
            'meta' => ['info' => ['length' => 1, 'name' => 'inner']],
        ];
        $decoded = bencode_decode(bencode_encode($torrent));
        $this->assertNotFalse($decoded);
        $this->assertSame(bencode_encode($topInfo), $decoded['info_raw']);
    }

    ////	Malformed input -> false

    public function testTrailingBytesRejected(): void
    {
        $this->assertFalse(bencode_decode('i42eX'));
        $this->assertFalse(bencode_decode('lee'));
    }

    public function testEmptyInputRejected(): void
    {
        $this->assertFalse(bencode_decode(''));
    }

    public function testUnterminatedContainersRejected(): void
    {
        $this->assertFalse(bencode_decode('l'));
        $this->assertFalse(bencode_decode('li1e'));
        $this->assertFalse(bencode_decode('d'));
        $this->assertFalse(bencode_decode('d3:key'));
    }

    public function testUnterminatedIntegerRejected(): void
    {
        $this->assertFalse(bencode_decode('i42'));
    }

    public function testEmptyIntegerRejected(): void
    {
        $this->assertFalse(bencode_decode('ie'));
    }

    public function testNegativeZeroIntegerRejected(): void
    {
        $this->assertFalse(bencode_decode('i-0e'));
    }

    public function testNonNumericIntegerRejected(): void
    {
        $this->assertFalse(bencode_decode('iabce'));
        $this->assertFalse(bencode_decode('i1.5e'));
        $this->assertFalse(bencode_decode('i--1e'));
    }

    public function testLeadingZeroIntegerRejected(): void
    {
        $this->assertFalse(bencode_decode('i03e'));
        $this->assertFalse(bencode_decode('i00e'));
    }

    public function testStringLengthPastEndRejected(): void
    {
        $this->assertFalse(bencode_decode('5:abc'));
        $this->assertFalse(bencode_decode('10:short'));
    }

    public function testNonDigitStringLengthRejected(): void
    {
        $this->assertFalse(bencode_decode('x:abc'));
        $this->assertFalse(bencode_decode('-1:abc'));
    }

    public function testLeadingZeroStringLengthRejected(): void
    {
        $this->assertFalse(bencode_decode('05:hello'));
    }

    public function testStringWithoutColonRejected(): void
    {
        $this->assertFalse(bencode_decode('5'));
    }

    public function testDictNonStringKeyRejected(): void
    {
        // A dict key must be a byte string; an integer key is malformed.
        $this->assertFalse(bencode_decode('di1ei2ee'));
    }

    public function testDictDanglingKeyRejected(): void
    {
        // Key present, value missing before the close.
        $this->assertFalse(bencode_decode('d3:keye'));
    }

    public function testGarbageRejected(): void
    {
        $this->assertFalse(bencode_decode('x'));
        $this->assertFalse(bencode_decode('e'));
    }

    ////	Depth bomb

    public function testDepthBombRejected(): void
    {
        // Nest lists deeper than the recursion cap; must fail, not crash.
        $depth = 200;
        $data = str_repeat('l', $depth).str_repeat('e', $depth);
        $this->assertFalse(bencode_decode($data));
    }

    public function testDeepButWithinLimitDecodes(): void
    {
        // Exactly at a depth comfortably under the cap, it should still decode.
        $depth = 30;
        $data = str_repeat('l', $depth).'i1e'.str_repeat('e', $depth);
        $decoded = bencode_decode($data);
        $this->assertNotFalse($decoded);
    }
}
