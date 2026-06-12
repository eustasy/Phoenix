<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class TorrentNormalizeMetaTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/torrent.normalize.meta.php';
    }

    ////	filename

    public function testFilenameNullPassedThroughAsNull(): void
    {
        $result = \torrent_normalize_meta(null, null, null, null);
        $this->assertNull($result['filename']);
    }

    public function testFilenameStringPassedThroughUnchanged(): void
    {
        $result = \torrent_normalize_meta('my.movie.mkv', null, null, null);
        $this->assertSame('my.movie.mkv', $result['filename']);
    }

    ////	files

    public function testFilesNullColumnGivesNull(): void
    {
        $result = \torrent_normalize_meta(null, null, null, null);
        $this->assertNull($result['files']);
    }

    public function testFilesInvalidJsonGivesNull(): void
    {
        $result = \torrent_normalize_meta(null, 'not-json', null, null);
        $this->assertNull($result['files']);
    }

    public function testFilesJsonObjectNotListGivesNull(): void
    {
        // A JSON object (dict) at the top level must be rejected — only lists.
        $result = \torrent_normalize_meta(null, '{"path":"a","length":1}', null, null);
        $this->assertNull($result['files']);
    }

    public function testFilesEmptyListGivesEmptyArray(): void
    {
        $result = \torrent_normalize_meta(null, '[]', null, null);
        $this->assertSame([], $result['files']);
    }

    public function testFilesValidEntriesDecoded(): void
    {
        $json = json_encode([
            ['path' => 'a/b.mkv', 'length' => 123],
            ['path' => 'a/c.srt', 'length' => 456],
        ]);
        $this->assertIsString($json);

        $result = \torrent_normalize_meta(null, $json, null, null);
        $this->assertSame([
            ['path' => 'a/b.mkv', 'length' => 123],
            ['path' => 'a/c.srt', 'length' => 456],
        ], $result['files']);
    }

    public function testFilesLengthCastToInt(): void
    {
        // json_encode may produce numeric strings; length must always be int.
        $result = \torrent_normalize_meta(null, '[{"path":"f.mkv","length":99}]', null, null);
        $this->assertIsArray($result['files']);
        $this->assertSame(99, $result['files'][0]['length']);
        $this->assertIsInt($result['files'][0]['length']);
    }

    public function testFilesPathCastToString(): void
    {
        $result = \torrent_normalize_meta(null, '[{"path":"dir/file.txt","length":1}]', null, null);
        $this->assertIsArray($result['files']);
        $this->assertIsString($result['files'][0]['path']);
    }

    public function testFilesMalformedElementsDropped(): void
    {
        // Elements missing 'path' or 'length', or with non-numeric length, are silently dropped.
        $json = json_encode([
            ['path' => 'good.mkv', 'length' => 100],
            ['path' => 'missing-length'],
            ['length' => 200],
            ['path' => 'bad-length.mkv', 'length' => 'not-numeric'],
        ]);
        $this->assertIsString($json);

        $result = \torrent_normalize_meta(null, $json, null, null);
        $this->assertIsArray($result['files']);
        $this->assertCount(1, $result['files']);
        $this->assertSame('good.mkv', $result['files'][0]['path']);
    }

    ////	trackers

    public function testTrackersNullColumnGivesNull(): void
    {
        $result = \torrent_normalize_meta(null, null, null, null);
        $this->assertNull($result['trackers']);
    }

    public function testTrackersSplitOnNewlines(): void
    {
        $result = \torrent_normalize_meta(null, null, "https://t1.example/\nhttps://t2.example/", null);
        $this->assertSame(['https://t1.example/', 'https://t2.example/'], $result['trackers']);
    }

    public function testTrackersTrimmedAndBlanksDropped(): void
    {
        $result = \torrent_normalize_meta(null, null, "  https://t1.example/  \n\n  \nhttps://t2.example/\n", null);
        $this->assertSame(['https://t1.example/', 'https://t2.example/'], $result['trackers']);
    }

    public function testTrackersAllBlankLinesGivesEmptyList(): void
    {
        $result = \torrent_normalize_meta(null, null, "\n\n  \n", null);
        $this->assertSame([], $result['trackers']);
    }

    ////	webseeds

    public function testWebseedsNullColumnGivesNull(): void
    {
        $result = \torrent_normalize_meta(null, null, null, null);
        $this->assertNull($result['webseeds']);
    }

    public function testWebseedsSplitOnNewlines(): void
    {
        $result = \torrent_normalize_meta(null, null, null, "https://seed1.example/\nhttps://seed2.example/");
        $this->assertSame(['https://seed1.example/', 'https://seed2.example/'], $result['webseeds']);
    }

    public function testWebseedsTrimmedAndBlanksDropped(): void
    {
        $result = \torrent_normalize_meta(null, null, null, "  https://seed.example/  \n\n");
        $this->assertSame(['https://seed.example/'], $result['webseeds']);
    }

    ////	shape

    public function testReturnShapeHasAllFourKeys(): void
    {
        $result = \torrent_normalize_meta(null, null, null, null);
        $this->assertArrayHasKey('filename', $result);
        $this->assertArrayHasKey('files', $result);
        $this->assertArrayHasKey('trackers', $result);
        $this->assertArrayHasKey('webseeds', $result);
    }

    public function testFullRoundTrip(): void
    {
        $files = json_encode([['path' => 'movie.mkv', 'length' => 1073741824]]);
        $this->assertIsString($files);

        $result = \torrent_normalize_meta(
            'movie.mkv',
            $files,
            "https://tracker.example/announce\nhttps://tracker2.example/announce",
            'https://seed.example/movie.mkv',
        );

        $this->assertSame('movie.mkv', $result['filename']);
        $this->assertSame([['path' => 'movie.mkv', 'length' => 1073741824]], $result['files']);
        $this->assertSame(['https://tracker.example/announce', 'https://tracker2.example/announce'], $result['trackers']);
        $this->assertSame(['https://seed.example/movie.mkv'], $result['webseeds']);
    }
}
