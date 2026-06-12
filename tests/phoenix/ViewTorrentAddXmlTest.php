<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class ViewTorrentAddXmlTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/xml.torrent.add.php';
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{user: string, info_hash: string, name: string|null, size: int, listed: int, filename: string|null, files: list<array{path: string, length: int}>|null, trackers: list<string>|null, webseeds: list<string>|null}
     */
    private function torrent(array $overrides = []): array
    {
        return array_merge([
            'user' => 'alice',
            'info_hash' => str_repeat('ab', 20),
            'name' => 'Test Torrent',
            'size' => 1024,
            'listed' => 1,
            'filename' => null,
            'files' => null,
            'trackers' => null,
            'webseeds' => null,
        ], $overrides);
    }

    public function testRendersWellFormedXml(): void
    {
        $xml = view_torrent_add_xml($this->torrent());

        $this->assertStringStartsWith('<?xml', $xml);
        $doc = simplexml_load_string($xml);
        $this->assertNotFalse($doc);
        $this->assertSame('alice', (string) $doc->user);
        $this->assertSame(str_repeat('ab', 20), (string) $doc->info_hash);
        $this->assertSame('Test Torrent', (string) $doc->name);
        $this->assertSame('1024', (string) $doc->size);
        $this->assertSame('1', (string) $doc->listed);
    }

    public function testEscapesSpecialCharacters(): void
    {
        $torrent = $this->torrent();
        $torrent['user'] = 'a & b';
        $torrent['name'] = 'x < y > z';

        $doc = simplexml_load_string(view_torrent_add_xml($torrent));

        // Body must be parseable; the entities round-trip back to the original.
        $this->assertNotFalse($doc);
        $this->assertSame('a & b', (string) $doc->user);
        $this->assertSame('x < y > z', (string) $doc->name);
    }

    public function testNullNameRendersEmptyElement(): void
    {
        $torrent = $this->torrent();
        $torrent['name'] = null;

        $doc = simplexml_load_string(view_torrent_add_xml($torrent));

        $this->assertNotFalse($doc);
        $this->assertSame('', (string) $doc->name);
    }

    public function testNullMetaElementsOmitted(): void
    {
        // With all meta null, none of the four meta elements appear at all
        // (omission, not an empty element).
        $xml = view_torrent_add_xml($this->torrent());

        $this->assertStringNotContainsString('<filename>', $xml);
        $this->assertStringNotContainsString('<files>', $xml);
        $this->assertStringNotContainsString('<trackers>', $xml);
        $this->assertStringNotContainsString('<webseeds>', $xml);
    }

    public function testMetaElementsRendered(): void
    {
        $doc = simplexml_load_string(view_torrent_add_xml($this->torrent([
            'filename' => 'movie.mkv',
            'files' => [
                ['path' => 'a/b.mkv', 'length' => 42],
                ['path' => 'c.txt', 'length' => 7],
            ],
            'trackers' => ['http://a/announce', 'http://b/announce'],
            'webseeds' => ['http://seed/'],
        ])));

        $this->assertNotFalse($doc);
        $this->assertSame('movie.mkv', (string) $doc->filename);

        $this->assertCount(2, $doc->files->file);
        $this->assertSame('a/b.mkv', (string) $doc->files->file[0]->path);
        $this->assertSame('42', (string) $doc->files->file[0]->length);
        $this->assertSame('c.txt', (string) $doc->files->file[1]->path);
        $this->assertSame('7', (string) $doc->files->file[1]->length);

        $this->assertCount(2, $doc->trackers->tracker);
        $this->assertSame('http://a/announce', (string) $doc->trackers->tracker[0]);
        $this->assertSame('http://b/announce', (string) $doc->trackers->tracker[1]);

        $this->assertCount(1, $doc->webseeds->webseed);
        $this->assertSame('http://seed/', (string) $doc->webseeds->webseed[0]);
    }

    public function testMetaStringsAreEscaped(): void
    {
        // A filename and a file path with XML-significant characters must
        // round-trip through entities rather than break the document.
        $doc = simplexml_load_string(view_torrent_add_xml($this->torrent([
            'filename' => 'a & b <tag>.mkv',
            'files' => [['path' => 'x/<y>&z.txt', 'length' => 1]],
        ])));

        $this->assertNotFalse($doc);
        $this->assertSame('a & b <tag>.mkv', (string) $doc->filename);
        $this->assertSame('x/<y>&z.txt', (string) $doc->files->file[0]->path);
    }
}
