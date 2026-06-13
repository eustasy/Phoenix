<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class ViewTorrentsXmlTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/xml.torrents.php';
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{info_hash: string|null, user: string|null, name: string|null, size: int, listed: int, downloads: int, seeders: int, leechers: int, peers: int, traffic: int, filename: string|null, files: list<array{path: string, length: int}>|null, trackers: list<string>|null, webseeds: list<string>|null}
     */
    private function torrent(array $overrides = []): array
    {
        return array_merge([
            'info_hash' => str_repeat('ab', 20),
            'user' => 'alice',
            'name' => 'Test Torrent',
            'size' => 1024,
            'listed' => 1,
            'downloads' => 3,
            'seeders' => 2,
            'leechers' => 1,
            'peers' => 3,
            'traffic' => 3072,
            'filename' => null,
            'files' => null,
            'trackers' => null,
            'webseeds' => null,
        ], $overrides);
    }

    public function testRendersWellFormedXml(): void
    {
        $xml = view_torrents_xml([$this->torrent()]);
        $this->assertStringStartsWith('<?xml', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function testEmptyCollectionIsEmptyTorrentsElement(): void
    {
        $xml = view_torrents_xml([]);
        $this->assertStringEndsWith('<torrents></torrents>', $xml);
    }

    public function testRendersCoreAndOwnershipFields(): void
    {
        $xml = view_torrents_xml([$this->torrent()]);

        $this->assertStringContainsString('<info_hash>'.str_repeat('ab', 20).'</info_hash>', $xml);
        $this->assertStringContainsString('<user>alice</user>', $xml);
        $this->assertStringContainsString('<name>Test Torrent</name>', $xml);
        $this->assertStringContainsString('<size>1024</size>', $xml);
        $this->assertStringContainsString('<listed>1</listed>', $xml);
        $this->assertStringContainsString('<downloads>3</downloads>', $xml);
        $this->assertStringContainsString('<seeders>2</seeders>', $xml);
        $this->assertStringContainsString('<leechers>1</leechers>', $xml);
        $this->assertStringContainsString('<peers>3</peers>', $xml);
        $this->assertStringContainsString('<traffic>3072</traffic>', $xml);
    }

    public function testEscapesNameAndUser(): void
    {
        $xml = view_torrents_xml([$this->torrent([
            'name' => 'Tom & Jerry <1>',
            'user' => 'a&b',
        ])]);

        $this->assertStringContainsString('<name>Tom &amp; Jerry &lt;1&gt;</name>', $xml);
        $this->assertStringContainsString('<user>a&amp;b</user>', $xml);
        $this->assertStringNotContainsString('Tom & Jerry', $xml);
    }

    public function testNullNameAndUserRenderEmpty(): void
    {
        $xml = view_torrents_xml([$this->torrent(['name' => null, 'user' => null])]);
        $this->assertStringContainsString('<name></name>', $xml);
        $this->assertStringContainsString('<user></user>', $xml);
    }

    public function testOmitsNullMetaBlocks(): void
    {
        $xml = view_torrents_xml([$this->torrent()]);
        $this->assertStringNotContainsString('<filename>', $xml);
        $this->assertStringNotContainsString('<files>', $xml);
        $this->assertStringNotContainsString('<trackers>', $xml);
        $this->assertStringNotContainsString('<webseeds>', $xml);
    }

    public function testRendersMetaBlocksWhenPresent(): void
    {
        $xml = view_torrents_xml([$this->torrent([
            'filename' => 'movie.mkv',
            'files' => [['path' => 'a/b.mkv', 'length' => 42]],
            'trackers' => ['http://a/announce'],
            'webseeds' => ['http://seed/'],
        ])]);

        $this->assertStringContainsString('<filename>movie.mkv</filename>', $xml);
        $this->assertStringContainsString('<files><file><path>a/b.mkv</path><length>42</length></file></files>', $xml);
        $this->assertStringContainsString('<trackers><tracker>http://a/announce</tracker></trackers>', $xml);
        $this->assertStringContainsString('<webseeds><webseed>http://seed/</webseed></webseeds>', $xml);
    }

    public function testRendersMultipleTorrents(): void
    {
        $xml = view_torrents_xml([
            $this->torrent(['info_hash' => str_repeat('11', 20)]),
            $this->torrent(['info_hash' => str_repeat('22', 20)]),
        ]);

        $this->assertSame(2, substr_count($xml, '<torrent>'));
        $this->assertStringContainsString('<info_hash>'.str_repeat('11', 20).'</info_hash>', $xml);
        $this->assertStringContainsString('<info_hash>'.str_repeat('22', 20).'</info_hash>', $xml);
    }
}
