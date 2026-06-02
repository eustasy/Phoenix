<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use InvalidArgumentException;

class BencodeEncodeTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/bencode.encode.php';
    }

    ////	Scalars

    public function testInteger(): void
    {
        $this->assertSame('i42e', bencode_encode(42));
        $this->assertSame('i0e', bencode_encode(0));
        $this->assertSame('i-7e', bencode_encode(-7));
    }

    public function testBool(): void
    {
        // bencode has no boolean; the convenience mapping is to 0/1 integers.
        $this->assertSame('i1e', bencode_encode(true));
        $this->assertSame('i0e', bencode_encode(false));
    }

    public function testString(): void
    {
        $this->assertSame('5:hello', bencode_encode('hello'));
        $this->assertSame('0:', bencode_encode(''));
    }

    public function testBinaryStringUsesByteLength(): void
    {
        // hex2bin('00ff...') is 20 raw bytes including a NUL — the length
        // prefix must count bytes, and the body must survive unescaped.
        $raw = hex2bin(str_repeat('00ff', 5));
        $this->assertSame('10:'.$raw, bencode_encode($raw));
        $this->assertSame(10, strlen($raw));
    }

    public function testMultibyteCountsBytesNotCharacters(): void
    {
        // 'é' is two bytes in UTF-8.
        $this->assertSame('2:'."\xC3\xA9", bencode_encode("\xC3\xA9"));
    }

    ////	Containers

    public function testList(): void
    {
        $this->assertSame('li1ei2ei3ee', bencode_encode([1, 2, 3]));
    }

    public function testEmptyArrayIsEmptyList(): void
    {
        $this->assertSame('le', bencode_encode([]));
    }

    public function testNestedList(): void
    {
        $this->assertSame('li1el3:twoee', bencode_encode([1, ['two']]));
    }

    public function testDictSortsKeysByRawByteOrder(): void
    {
        // Deliberately out of order on input; spec requires sorted output.
        $out = bencode_encode(['peers6' => 'b', 'peers' => 'a', 'complete' => 1]);
        $this->assertSame('d8:completei1e5:peers1:a6:peers61:be', $out);
    }

    public function testDictKeyOrderingEdgeCasePrefix(): void
    {
        // 'peers' is a prefix of 'peers6'; the shorter key sorts first.
        $keys = array_keys($this->decodeTopLevelDict(
            bencode_encode(['peers6' => 'x', 'peers' => 'y']),
        ));
        $this->assertSame(['peers', 'peers6'], $keys);
    }

    public function testNumericStringKeysCoerceButStillEmit(): void
    {
        // PHP coerces the numeric-string key '7' to int 7; it must still emit
        // as the string key "7" with a correct length prefix.
        $this->assertSame('d1:7i1ee', bencode_encode(['7' => 1]));
    }

    public function testObjectIsAlwaysADict(): void
    {
        $this->assertSame('d1:ai1ee', bencode_encode((object) ['a' => 1]));
    }

    public function testEmptyObjectIsEmptyDict(): void
    {
        // The forced-dict form: an empty array would be 'le', but the scrape
        // files-dict must stay 'de' when there are no torrents.
        $this->assertSame('de', bencode_encode((object) []));
    }

    public function testObjectPreservesBinaryKeys(): void
    {
        // A raw 20-byte info_hash (with a leading NUL) survives the object cast
        // and emits with a correct byte-length prefix.
        $hash = hex2bin('00'.str_repeat('ff', 19));
        $out = bencode_encode((object) [$hash => 1]);
        $this->assertSame('d20:'.$hash.'i1ee', $out);
    }

    public function testNestedDictAndList(): void
    {
        $out = bencode_encode([
            'files' => ['a' => ['complete' => 2]],
            'peers' => [1, 2],
        ]);
        $this->assertSame('d5:filesd1:ad8:completei2eee5:peersli1ei2eee', $out);
    }

    ////	Errors

    public function testRejectsUnsupportedType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        bencode_encode(3.14);
    }

    public function testRejectsNull(): void
    {
        $this->expectException(InvalidArgumentException::class);
        bencode_encode(null);
    }

    ////	Helpers

    /** Minimal top-level dict decoder, just enough to read back key order. */
    private function decodeTopLevelDict(string $s): array
    {
        $out = [];
        $i = 1; // skip leading 'd'
        while ($s[$i] !== 'e') {
            $colon = strpos($s, ':', $i);
            $len = (int) substr($s, $i, $colon - $i);
            $key = substr($s, $colon + 1, $len);
            $i = $colon + 1 + $len;
            // Skip the value (only strings/ints used in these fixtures).
            if ($s[$i] === 'i') {
                $i = strpos($s, 'e', $i) + 1;
            } else {
                $c = strpos($s, ':', $i);
                $vlen = (int) substr($s, $i, $c - $i);
                $i = $c + 1 + $vlen;
            }
            $out[$key] = true;
        }

        return $out;
    }

}
