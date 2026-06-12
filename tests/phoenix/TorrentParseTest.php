<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class TorrentParseTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/bencode.encode.php';
        require_once __DIR__.'/../../src/functions/bencode.decode.php';
        require_once __DIR__.'/../../src/functions/torrent.parse.php';
    }

    ////	Fixtures
    // Every fixture is built in-test from a PHP structure and bencode_encode(),
    // so there are no binary .torrent files to maintain. Helpers keep the
    // assertions focused on the parsed shape rather than on encoding mechanics.

    /**
     * @param array<string, mixed> $info
     * @param array<string, mixed> $extra top-level keys (announce, url-list, ...)
     */
    private function torrent(array $info, array $extra = []): string
    {
        return bencode_encode(['info' => $info] + $extra);
    }

    ////	Single-file

    public function testSingleFile(): void
    {
        $info = [
            'name' => 'ubuntu.iso',
            'length' => 1234,
            'piece length' => 16384,
            'pieces' => str_repeat("\x00", 20),
        ];
        $parsed = torrent_parse($this->torrent($info, [
            'announce' => 'http://tracker.example/announce',
        ]));

        $this->assertNotFalse($parsed);
        $this->assertSame('ubuntu.iso', $parsed['name']);
        $this->assertSame('ubuntu.iso', $parsed['filename']);
        $this->assertSame(1234, $parsed['size']);
        $this->assertSame(
            [['path' => 'ubuntu.iso', 'length' => 1234]],
            $parsed['files'],
        );
        $this->assertSame(['http://tracker.example/announce'], $parsed['trackers']);
        $this->assertSame([], $parsed['webseeds']);
    }

    public function testInfoHashIsSha1OfInfoEncoding(): void
    {
        // The contract: info_hash = sha1 of bencode_encode of the info structure.
        $info = [
            'name' => 'thing',
            'length' => 42,
            'piece length' => 16384,
            'pieces' => str_repeat("\xAB", 20),
        ];
        $parsed = torrent_parse($this->torrent($info));

        $this->assertNotFalse($parsed);
        $this->assertSame(sha1(bencode_encode($info)), $parsed['info_hash']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $parsed['info_hash']);
    }

    ////	Multi-file

    public function testMultiFilePathJoinAndSizeSum(): void
    {
        $info = [
            'name' => 'collection',
            'files' => [
                ['length' => 100, 'path' => ['dir', 'a.txt']],
                ['length' => 250, 'path' => ['dir', 'sub', 'b.bin']],
                ['length' => 5, 'path' => ['c']],
            ],
            'piece length' => 16384,
            'pieces' => str_repeat("\x01", 20),
        ];
        $parsed = torrent_parse($this->torrent($info));

        $this->assertNotFalse($parsed);
        $this->assertSame('collection', $parsed['name']);
        $this->assertSame(355, $parsed['size']);
        $this->assertSame([
            ['path' => 'dir/a.txt', 'length' => 100],
            ['path' => 'dir/sub/b.bin', 'length' => 250],
            ['path' => 'c', 'length' => 5],
        ], $parsed['files']);
    }

    public function testMultiFileSkipsMalformedButKeepsValid(): void
    {
        // Entries with a missing path, non-array path, negative length, or
        // non-string path components are skipped; the survivors still parse.
        $info = [
            'name' => 'mixed',
            'files' => [
                ['length' => 10, 'path' => ['good.txt']],
                ['length' => 20], // no path -> skipped
                ['length' => -5, 'path' => ['neg']], // negative -> skipped
                ['length' => 30, 'path' => ['x', 7]], // non-string part -> skipped
                ['length' => 40, 'path' => ['ok', 'two.txt']],
            ],
        ];
        $parsed = torrent_parse($this->torrent($info));

        $this->assertNotFalse($parsed);
        $this->assertSame([
            ['path' => 'good.txt', 'length' => 10],
            ['path' => 'ok/two.txt', 'length' => 40],
        ], $parsed['files']);
        $this->assertSame(50, $parsed['size']);
    }

    public function testMultiFileWithNoValidEntriesIsFalse(): void
    {
        $info = [
            'name' => 'empty',
            'files' => [
                ['length' => 5], // no path
                ['path' => ['x']], // no length
            ],
        ];
        $this->assertFalse(torrent_parse($this->torrent($info)));
    }

    ////	name.utf-8 preference

    public function testNameUtf8Preferred(): void
    {
        $info = [
            'name' => 'legacy-name',
            'name.utf-8' => 'utf8-name',
            'length' => 1,
        ];
        $parsed = torrent_parse($this->torrent($info));

        $this->assertNotFalse($parsed);
        $this->assertSame('utf8-name', $parsed['name']);
        $this->assertSame('utf8-name', $parsed['filename']);
    }

    public function testPathUtf8Preferred(): void
    {
        $info = [
            'name' => 'd',
            'files' => [
                [
                    'length' => 1,
                    'path' => ['legacy', 'file'],
                    'path.utf-8' => ['utf8', 'file'],
                ],
            ],
        ];
        $parsed = torrent_parse($this->torrent($info));

        $this->assertNotFalse($parsed);
        $this->assertSame('utf8/file', $parsed['files'][0]['path']);
    }

    public function testNameAbsentIsNull(): void
    {
        $info = ['length' => 7];
        $parsed = torrent_parse($this->torrent($info));

        $this->assertNotFalse($parsed);
        $this->assertNull($parsed['name']);
        $this->assertNull($parsed['filename']);
        $this->assertSame(7, $parsed['size']);
        // Single-file falls back to an empty path when there is no name.
        $this->assertSame([['path' => '', 'length' => 7]], $parsed['files']);
    }

    ////	Trackers: announce + announce-list, order-preserving dedup

    public function testTrackersAnnouncePlusAnnounceListDedup(): void
    {
        $info = ['name' => 't', 'length' => 1];
        $parsed = torrent_parse($this->torrent($info, [
            'announce' => 'http://primary/announce',
            'announce-list' => [
                ['http://primary/announce'], // duplicate of announce -> dropped
                ['  http://second/announce  '], // trimmed
                ['http://third/announce', ''], // blank dropped
                ['http://second/announce'], // duplicate -> dropped
            ],
        ]));

        $this->assertNotFalse($parsed);
        $this->assertSame([
            'http://primary/announce',
            'http://second/announce',
            'http://third/announce',
        ], $parsed['trackers']);
    }

    public function testTrackersEmptyWhenNone(): void
    {
        $parsed = torrent_parse($this->torrent(['name' => 't', 'length' => 1]));
        $this->assertNotFalse($parsed);
        $this->assertSame([], $parsed['trackers']);
    }

    ////	Webseeds: url-list string and list forms (BEP 19)

    public function testWebseedsUrlListAsString(): void
    {
        $parsed = torrent_parse($this->torrent(['name' => 't', 'length' => 1], [
            'url-list' => 'http://seed.example/files/',
        ]));

        $this->assertNotFalse($parsed);
        $this->assertSame(['http://seed.example/files/'], $parsed['webseeds']);
    }

    public function testWebseedsUrlListAsListWithDedup(): void
    {
        $parsed = torrent_parse($this->torrent(['name' => 't', 'length' => 1], [
            'url-list' => [
                'http://seed-a/',
                '  http://seed-b/  ',
                '',
                'http://seed-a/',
            ],
        ]));

        $this->assertNotFalse($parsed);
        $this->assertSame(['http://seed-a/', 'http://seed-b/'], $parsed['webseeds']);
    }

    ////	Malformed -> false

    public function testMalformedBencodeIsFalse(): void
    {
        $this->assertFalse(torrent_parse('not-bencode'));
        $this->assertFalse(torrent_parse(''));
        $this->assertFalse(torrent_parse('i42eX')); // trailing bytes
    }

    public function testMissingInfoIsFalse(): void
    {
        $this->assertFalse(torrent_parse(bencode_encode([
            'announce' => 'http://x/announce',
        ])));
    }

    public function testNonDictInfoIsFalse(): void
    {
        // 'info' present but not a dict (a string here).
        $this->assertFalse(torrent_parse(bencode_encode([
            'info' => 'not-a-dict',
        ])));
    }

    public function testTopLevelNotDictIsFalse(): void
    {
        $this->assertFalse(torrent_parse(bencode_encode([1, 2, 3])));
    }

    public function testSingleFileNegativeLengthIsFalse(): void
    {
        $this->assertFalse(torrent_parse($this->torrent([
            'name' => 't',
            'length' => -1,
        ])));
    }

    public function testNoUsableFieldsIsFalse(): void
    {
        // An info dict with neither 'length' nor a valid 'files' list.
        $this->assertFalse(torrent_parse($this->torrent([
            'name' => 'no-data',
        ])));
    }

    public function testEmptyInfoDictIsFalse(): void
    {
        $this->assertFalse(torrent_parse(bencode_encode(['info' => (object) []])));
    }
}
