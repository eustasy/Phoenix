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

}
