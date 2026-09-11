<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class ViewScrapeBencodeTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/bencode.scrape.php';
    }

    /** @return array<string, array<string, int|string>> */
    private function fixture(): array
    {
        return [
            'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' => [
                'info_hash' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                'seeders' => 2,
                'leechers' => 1,
                'downloads' => 7,
            ],
        ];
    }

    public function testStartsWithFilesDictKey(): void
    {
        $out = view_scrape_bencode($this->fixture());
        $this->assertStringStartsWith('d5:filesd', $out);
    }

    public function testInfoHashEncodedAsRawTwentyBytes(): void
    {
        $out = view_scrape_bencode($this->fixture());
        // BEP 48: key is the raw 20-byte info_hash, prefixed by '20:'.
        $rawHash = hex2bin('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
        $this->assertStringContainsString('20:'.$rawHash, $out);
    }

    public function testStatsDictUsesBepKeysAndCounts(): void
    {
        $out = view_scrape_bencode($this->fixture());
        $this->assertStringContainsString('8:completei2e', $out);
        $this->assertStringContainsString('10:downloadedi7e', $out);
        $this->assertStringContainsString('10:incompletei1e', $out);
    }

    public function testSingleTorrentMatchesExactBencode(): void
    {
        $out = view_scrape_bencode($this->fixture());
        $rawHash = hex2bin('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
        $expected = 'd5:filesd'.
            '20:'.$rawHash.
                'd8:completei2e10:downloadedi7e10:incompletei1ee'.
            'ee';
        $this->assertSame($expected, $out);
    }

    public function testEmptyScrapeStillBalancesContainer(): void
    {
        // Boundary: with no torrents, the files dict is empty.
        // Correct bencode: outer dict with "files" key pointing to empty dict.
        // d 5:files d e e
        //   ^outer  ^files dict  ^close files  ^close outer
        $out = view_scrape_bencode([]);
        $expected = 'd' . '5:files' . 'd' . 'e' . 'e';
        $this->assertSame($expected, $out);
    }

    public function testMultipleTorrents(): void
    {
        $scrape = [
            'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' => [
                'info_hash' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                'seeders' => 2,
                'leechers' => 1,
                'downloads' => 7,
            ],
            'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb' => [
                'info_hash' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                'seeders' => 5,
                'leechers' => 3,
                'downloads' => 12,
            ],
        ];

        $out = view_scrape_bencode($scrape);

        // Should contain both info_hashes
        $this->assertStringContainsString(hex2bin('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'), $out);
        $this->assertStringContainsString(hex2bin('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'), $out);

        // Should contain both sets of stats
        $this->assertStringContainsString('8:completei2e', $out);
        $this->assertStringContainsString('8:completei5e', $out);
        $this->assertStringContainsString('10:incompletei1e', $out);
        $this->assertStringContainsString('10:incompletei3e', $out);
    }

    /** @return array<string, array{int, int, int}> */
    public static function countProvider(): array
    {
        return [
            'zeroes' => [0, 0, 0],
            'large' => [999999, 888888, 777777],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('countProvider')]
    public function testCountsSerialiseAsBencodeIntegers(int $seeders, int $leechers, int $downloads): void
    {
        // Bencode integers are unpadded and unbounded, so the boundary cases
        // are the same assertion with different numbers.
        $hash = str_repeat('c', 40);
        $out = view_scrape_bencode([$hash => [
            'info_hash' => $hash,
            'seeders' => $seeders,
            'leechers' => $leechers,
            'downloads' => $downloads,
        ]]);

        $this->assertStringContainsString('8:complete'.'i'.$seeders.'e', $out);
        $this->assertStringContainsString('10:incomplete'.'i'.$leechers.'e', $out);
        $this->assertStringContainsString('10:downloaded'.'i'.$downloads.'e', $out);
    }

    public function testFlagsCarryMinRequestIntervalOnlyWhenNonZero(): void
    {
        // BEP 48: the throttle hint rides in a `flags` dict as a bencode integer.
        $with = view_scrape_bencode($this->fixture(), 1800);
        $this->assertStringContainsString('5:flags', $with);
        $this->assertStringContainsString('20:min_request_intervali1800e', $with);

        // Zero omits the dict entirely.
        $without = view_scrape_bencode($this->fixture(), 0);
        $this->assertStringNotContainsString('flags', $without);
    }

}
