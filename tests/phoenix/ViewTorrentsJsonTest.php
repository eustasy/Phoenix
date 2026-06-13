<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class ViewTorrentsJsonTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/json.torrents.php';
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

    public function testReturnsValidJson(): void
    {
        $this->assertJson(view_torrents_json([$this->torrent()]));
    }

    public function testEmptyCollectionIsEmptyList(): void
    {
        $decoded = json_decode(view_torrents_json([]), true);
        $this->assertSame(['torrents' => []], $decoded);
    }

    public function testWrapsRowsUnderTorrentsKey(): void
    {
        $decoded = json_decode(view_torrents_json([$this->torrent()]), true);

        $this->assertArrayHasKey('torrents', $decoded);
        $this->assertCount(1, $decoded['torrents']);

        $row = $decoded['torrents'][0];
        $this->assertSame(str_repeat('ab', 20), $row['info_hash']);
        $this->assertSame('alice', $row['user']);
        $this->assertSame('Test Torrent', $row['name']);
        $this->assertSame(1024, $row['size']);
        $this->assertSame(1, $row['listed']);
        $this->assertSame(3, $row['downloads']);
        $this->assertSame(2, $row['seeders']);
        $this->assertSame(1, $row['leechers']);
        $this->assertSame(3, $row['peers']);
        $this->assertSame(3072, $row['traffic']);
    }

    public function testNullUserAndNameSurvive(): void
    {
        $decoded = json_decode(view_torrents_json([$this->torrent(['user' => null, 'name' => null])]), true);
        $row = $decoded['torrents'][0];

        $this->assertArrayHasKey('user', $row);
        $this->assertNull($row['user']);
        $this->assertArrayHasKey('name', $row);
        $this->assertNull($row['name']);
    }

    public function testNullMetaFieldsAreNull(): void
    {
        $row = json_decode(view_torrents_json([$this->torrent()]), true)['torrents'][0];

        foreach (['filename', 'files', 'trackers', 'webseeds'] as $key) {
            $this->assertArrayHasKey($key, $row);
            $this->assertNull($row[$key]);
        }
    }

    public function testMetaFieldsRendered(): void
    {
        $row = json_decode(view_torrents_json([$this->torrent([
            'filename' => 'movie.mkv',
            'files' => [['path' => 'a/b.mkv', 'length' => 42]],
            'trackers' => ['http://a/announce'],
            'webseeds' => ['http://seed/'],
        ])]), true)['torrents'][0];

        $this->assertSame('movie.mkv', $row['filename']);
        $this->assertSame([['path' => 'a/b.mkv', 'length' => 42]], $row['files']);
        $this->assertSame(['http://a/announce'], $row['trackers']);
        $this->assertSame(['http://seed/'], $row['webseeds']);
    }

    public function testRendersMultipleRowsInOrder(): void
    {
        $decoded = json_decode(view_torrents_json([
            $this->torrent(['info_hash' => str_repeat('11', 20), 'name' => 'First']),
            $this->torrent(['info_hash' => str_repeat('22', 20), 'name' => 'Second']),
        ]), true);

        $this->assertSame(['First', 'Second'], array_column($decoded['torrents'], 'name'));
    }
}
