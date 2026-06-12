<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class ViewTorrentAddJsonTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/json.torrent.add.php';
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

    public function testReturnsValidJson(): void
    {
        $this->assertJson(view_torrent_add_json($this->torrent()));
    }

    public function testIncludesTorrentObject(): void
    {
        $decoded = json_decode(view_torrent_add_json($this->torrent()), true);

        $this->assertIsArray($decoded['torrent']);
        $this->assertSame('alice', $decoded['torrent']['user']);
        $this->assertSame(str_repeat('ab', 20), $decoded['torrent']['info_hash']);
        $this->assertSame('Test Torrent', $decoded['torrent']['name']);
        $this->assertSame(1024, $decoded['torrent']['size']);
        $this->assertSame(1, $decoded['torrent']['listed']);
    }

    public function testNullNameSurvives(): void
    {
        $torrent = $this->torrent();
        $torrent['name'] = null;
        $decoded = json_decode(view_torrent_add_json($torrent), true);

        $this->assertArrayHasKey('name', $decoded['torrent']);
        $this->assertNull($decoded['torrent']['name']);
    }
}
