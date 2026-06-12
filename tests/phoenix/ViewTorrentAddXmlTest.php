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

    /** @return array{user: string, info_hash: string, name: string|null, size: int, listed: int} */
    private function torrent(): array
    {
        return [
            'user' => 'alice',
            'info_hash' => str_repeat('ab', 20),
            'name' => 'Test Torrent',
            'size' => 1024,
            'listed' => 1,
        ];
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
}
