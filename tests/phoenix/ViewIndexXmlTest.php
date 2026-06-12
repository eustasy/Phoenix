<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class ViewIndexXmlTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/xml.index.php';
    }

    /** @return list<array<string, mixed>> */
    private function fixture(): array
    {
        return [[
            'info_hash' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'name' => 'Test Torrent',
            'size' => 1024,
            'downloads' => 7,
            'seeders' => 2,
            'leechers' => 1,
            'peers' => 3,
            'traffic' => 7168,
        ]];
    }

    public function testEmptyIndexYieldsEmptyTorrentsElement(): void
    {
        $xml = view_index_xml([]);
        $this->assertSame('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><torrents></torrents>', $xml);
    }

    public function testOutputWrappedInTorrentsElement(): void
    {
        $xml = view_index_xml($this->fixture());
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><torrents>', $xml);
        $this->assertStringEndsWith('</torrents>', $xml);
    }

    public function testSingleTorrentRendersAllFields(): void
    {
        $xml = view_index_xml($this->fixture());
        $this->assertStringContainsString('<info_hash>aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa</info_hash>', $xml);
        $this->assertStringContainsString('<name>Test Torrent</name>', $xml);
        $this->assertStringContainsString('<size>1024</size>', $xml);
        $this->assertStringContainsString('<downloads>7</downloads>', $xml);
        $this->assertStringContainsString('<seeders>2</seeders>', $xml);
        $this->assertStringContainsString('<leechers>1</leechers>', $xml);
        $this->assertStringContainsString('<peers>3</peers>', $xml);
        $this->assertStringContainsString('<traffic>7168</traffic>', $xml);
    }

    public function testTorrentNameIsXmlEscaped(): void
    {
        $index = [[
            'info_hash' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'name' => 'A & B <Test>',
            'size' => 0, 'downloads' => 0, 'seeders' => 0,
            'leechers' => 0, 'peers' => 0, 'traffic' => 0,
        ]];
        $xml = view_index_xml($index);
        $this->assertStringContainsString('<name>A &amp; B &lt;Test&gt;</name>', $xml);
        $this->assertStringNotContainsString('<name>A & B', $xml);
    }

    public function testMultipleTorrentsEachGetTheirOwnElement(): void
    {
        $index = $this->fixture();
        $index[] = [
            'info_hash' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            'name' => 'Second Torrent',
            'size' => 0, 'downloads' => 0, 'seeders' => 0,
            'leechers' => 0, 'peers' => 0, 'traffic' => 0,
        ];
        $xml = view_index_xml($index);
        $this->assertSame(2, substr_count($xml, '<torrent>'));
        $this->assertStringContainsString('<info_hash>bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb</info_hash>', $xml);
    }

    ////	show_meta = false (default) — output unchanged

    public function testMetaElementsAbsentByDefault(): void
    {
        $index = $this->fixture();
        $index[0]['filename'] = 'test.mkv';
        $index[0]['files'] = [['path' => 'test.mkv', 'length' => 1024]];
        $index[0]['trackers'] = ['https://tracker.example/announce'];
        $index[0]['webseeds'] = ['https://seed.example/test.mkv'];

        $xml = view_index_xml($index);

        $this->assertStringNotContainsString('<filename>', $xml);
        $this->assertStringNotContainsString('<files>', $xml);
        $this->assertStringNotContainsString('<trackers>', $xml);
        $this->assertStringNotContainsString('<webseeds>', $xml);
    }

    public function testMetaElementsAbsentWhenShowMetaFalse(): void
    {
        $index = $this->fixture();
        $index[0]['filename'] = 'test.mkv';
        $index[0]['files'] = [['path' => 'test.mkv', 'length' => 1024]];
        $index[0]['trackers'] = ['https://tracker.example/announce'];
        $index[0]['webseeds'] = ['https://seed.example/test.mkv'];

        $xml = view_index_xml($index, false);

        $this->assertStringNotContainsString('<filename>', $xml);
        $this->assertStringNotContainsString('<files>', $xml);
        $this->assertStringNotContainsString('<trackers>', $xml);
        $this->assertStringNotContainsString('<webseeds>', $xml);
    }

    ////	show_meta = true — meta elements emitted

    public function testFilenameEmittedWhenShowMetaTrue(): void
    {
        $index = $this->fixture();
        $index[0]['filename'] = 'my.movie.mkv';
        $index[0]['files'] = null;
        $index[0]['trackers'] = null;
        $index[0]['webseeds'] = null;

        $xml = view_index_xml($index, true);
        $this->assertStringContainsString('<filename>my.movie.mkv</filename>', $xml);
    }

    public function testFilenameOmittedWhenNullWithShowMetaTrue(): void
    {
        $index = $this->fixture();
        $index[0]['filename'] = null;
        $index[0]['files'] = null;
        $index[0]['trackers'] = null;
        $index[0]['webseeds'] = null;

        $xml = view_index_xml($index, true);
        $this->assertStringNotContainsString('<filename>', $xml);
    }

    public function testFilenameIsXmlEscapedWithShowMeta(): void
    {
        $index = $this->fixture();
        $index[0]['filename'] = 'A & B <Test>.mkv';
        $index[0]['files'] = null;
        $index[0]['trackers'] = null;
        $index[0]['webseeds'] = null;

        $xml = view_index_xml($index, true);
        $this->assertStringContainsString('<filename>A &amp; B &lt;Test&gt;.mkv</filename>', $xml);
        $this->assertStringNotContainsString('<filename>A & B', $xml);
    }

    public function testFilesEmittedWhenShowMetaTrue(): void
    {
        $index = $this->fixture();
        $index[0]['filename'] = null;
        $index[0]['files'] = [
            ['path' => 'dir/movie.mkv', 'length' => 1073741824],
            ['path' => 'dir/subs.srt',  'length' => 8192],
        ];
        $index[0]['trackers'] = null;
        $index[0]['webseeds'] = null;

        $xml = view_index_xml($index, true);
        $this->assertStringContainsString('<files>', $xml);
        $this->assertStringContainsString('<file>', $xml);
        $this->assertStringContainsString('<path>dir/movie.mkv</path>', $xml);
        $this->assertStringContainsString('<length>1073741824</length>', $xml);
        $this->assertStringContainsString('<path>dir/subs.srt</path>', $xml);
        $this->assertStringContainsString('<length>8192</length>', $xml);
        $this->assertSame(2, substr_count($xml, '<file>'));
    }

    public function testFilesPathIsXmlEscapedWithShowMeta(): void
    {
        $index = $this->fixture();
        $index[0]['filename'] = null;
        $index[0]['files'] = [['path' => 'a & b/c<d>.mkv', 'length' => 1]];
        $index[0]['trackers'] = null;
        $index[0]['webseeds'] = null;

        $xml = view_index_xml($index, true);
        $this->assertStringContainsString('<path>a &amp; b/c&lt;d&gt;.mkv</path>', $xml);
    }

    public function testFilesOmittedWhenNullWithShowMetaTrue(): void
    {
        $index = $this->fixture();
        $index[0]['filename'] = null;
        $index[0]['files'] = null;
        $index[0]['trackers'] = null;
        $index[0]['webseeds'] = null;

        $xml = view_index_xml($index, true);
        $this->assertStringNotContainsString('<files>', $xml);
    }

    public function testTrackersEmittedWhenShowMetaTrue(): void
    {
        $index = $this->fixture();
        $index[0]['filename'] = null;
        $index[0]['files'] = null;
        $index[0]['trackers'] = ['https://tracker1.example/announce', 'https://tracker2.example/announce'];
        $index[0]['webseeds'] = null;

        $xml = view_index_xml($index, true);
        $this->assertStringContainsString('<trackers>', $xml);
        $this->assertStringContainsString('<tracker>https://tracker1.example/announce</tracker>', $xml);
        $this->assertStringContainsString('<tracker>https://tracker2.example/announce</tracker>', $xml);
        $this->assertSame(2, substr_count($xml, '<tracker>'));
    }

    public function testTrackersOmittedWhenNullWithShowMetaTrue(): void
    {
        $index = $this->fixture();
        $index[0]['filename'] = null;
        $index[0]['files'] = null;
        $index[0]['trackers'] = null;
        $index[0]['webseeds'] = null;

        $xml = view_index_xml($index, true);
        $this->assertStringNotContainsString('<trackers>', $xml);
    }

    public function testTrackersAreXmlEscapedWithShowMeta(): void
    {
        $index = $this->fixture();
        $index[0]['filename'] = null;
        $index[0]['files'] = null;
        $index[0]['trackers'] = ['https://example.com/ann?a=1&b=2'];
        $index[0]['webseeds'] = null;

        $xml = view_index_xml($index, true);
        $this->assertStringContainsString('<tracker>https://example.com/ann?a=1&amp;b=2</tracker>', $xml);
    }

    public function testWebseedsEmittedWhenShowMetaTrue(): void
    {
        $index = $this->fixture();
        $index[0]['filename'] = null;
        $index[0]['files'] = null;
        $index[0]['trackers'] = null;
        $index[0]['webseeds'] = ['https://seed1.example/', 'https://seed2.example/'];

        $xml = view_index_xml($index, true);
        $this->assertStringContainsString('<webseeds>', $xml);
        $this->assertStringContainsString('<webseed>https://seed1.example/</webseed>', $xml);
        $this->assertStringContainsString('<webseed>https://seed2.example/</webseed>', $xml);
        $this->assertSame(2, substr_count($xml, '<webseed>'));
    }

    public function testWebseedsOmittedWhenNullWithShowMetaTrue(): void
    {
        $index = $this->fixture();
        $index[0]['filename'] = null;
        $index[0]['files'] = null;
        $index[0]['trackers'] = null;
        $index[0]['webseeds'] = null;

        $xml = view_index_xml($index, true);
        $this->assertStringNotContainsString('<webseeds>', $xml);
    }

    public function testWebseedsAreXmlEscapedWithShowMeta(): void
    {
        $index = $this->fixture();
        $index[0]['filename'] = null;
        $index[0]['files'] = null;
        $index[0]['trackers'] = null;
        $index[0]['webseeds'] = ['https://example.com/seed?a=1&b=2'];

        $xml = view_index_xml($index, true);
        $this->assertStringContainsString('<webseed>https://example.com/seed?a=1&amp;b=2</webseed>', $xml);
    }

    public function testMagnetEmittedAndEscapedWithoutMetaFlag(): void
    {
        $index = $this->fixture();
        $index[0]['magnet'] = 'magnet:?xt=urn:btih:'.str_repeat('a', 40).'&dn=Test';

        $xml = view_index_xml($index);
        $this->assertStringContainsString(
            '<magnet>magnet:?xt=urn:btih:'.str_repeat('a', 40).'&amp;dn=Test</magnet>',
            $xml,
        );
    }

    public function testMagnetEmittedWithMetaFlag(): void
    {
        $index = $this->fixture();
        $index[0]['magnet'] = 'magnet:?xt=urn:btih:'.str_repeat('a', 40);

        $xml = view_index_xml($index, true);
        $this->assertStringContainsString('<magnet>magnet:?xt=urn:btih:'.str_repeat('a', 40).'</magnet>', $xml);
    }

    public function testMagnetOmittedWhenAbsent(): void
    {
        $xml = view_index_xml($this->fixture());
        $this->assertStringNotContainsString('<magnet>', $xml);
    }

}
