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

    public function testNullMetaFieldsAreNull(): void
    {
        // The meta keys are always present in the object; absent meta is null.
        $decoded = json_decode(view_torrent_add_json($this->torrent()), true);

        foreach (['filename', 'files', 'trackers', 'webseeds'] as $key) {
            $this->assertArrayHasKey($key, $decoded['torrent']);
            $this->assertNull($decoded['torrent'][$key]);
        }
    }

    public function testMetaFieldsRendered(): void
    {
        $decoded = json_decode(view_torrent_add_json($this->torrent([
            'filename' => 'movie.mkv',
            'files' => [
                ['path' => 'a/b.mkv', 'length' => 42],
                ['path' => 'c.txt', 'length' => 7],
            ],
            'trackers' => ['http://a/announce', 'http://b/announce'],
            'webseeds' => ['http://seed/'],
        ])), true);

        $this->assertSame('movie.mkv', $decoded['torrent']['filename']);
        $this->assertSame([
            ['path' => 'a/b.mkv', 'length' => 42],
            ['path' => 'c.txt', 'length' => 7],
        ], $decoded['torrent']['files']);
        $this->assertSame(['http://a/announce', 'http://b/announce'], $decoded['torrent']['trackers']);
        $this->assertSame(['http://seed/'], $decoded['torrent']['webseeds']);
    }
}
