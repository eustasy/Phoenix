<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/model/torrent.update.php';
require_once __DIR__.'/../../src/model/torrent.add.php';
require_once __DIR__.'/../../src/model/torrent.select.one.php';

class TorrentUpdateTest extends PhoenixTestCase
{
    private const HASH = '__TEST_update__';

    protected function tearDown(): void
    {
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` LIKE \'__TEST_%\';',
        );
        parent::tearDown();
    }

    private function seed(?string $user): void
    {
        \torrent_add(self::$connection, self::$settings, [
            'user' => $user,
            'info_hash' => self::HASH,
            'name' => 'Original',
            'size' => 100,
            'listed' => 1,
            'filename' => null,
            'files' => null,
            'trackers' => null,
            'webseeds' => null,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'info_hash' => self::HASH,
            'name' => 'Renamed',
            'size' => 200,
            'listed' => 0,
            'filename' => null,
            'files' => null,
            'trackers' => null,
            'webseeds' => null,
        ], $overrides);
    }

    public function testUpdatesEditableFieldsAndPreservesIdentity(): void
    {
        $this->seed('alice');

        $this->assertTrue(\torrent_update(self::$connection, self::$settings, $this->payload([
            'filename' => 'f.iso',
        ]), null));

        $row = \torrent_select_one(self::$connection, self::$settings, self::HASH);
        $this->assertIsArray($row);
        $this->assertSame('Renamed', $row['name']);
        $this->assertSame(200, $row['size']);
        $this->assertSame(0, $row['listed']);
        $this->assertSame('f.iso', $row['filename']);
        // Identity columns are never touched.
        $this->assertSame('alice', $row['user']);
        $this->assertSame(self::HASH, $row['info_hash']);
    }

    public function testOwnerGuardBlocksADifferentUser(): void
    {
        $this->seed('alice');

        // bob's update is guarded by AND user='bob' → 0 rows match. The
        // statement still executes (true), but nothing changes.
        $this->assertTrue(\torrent_update(self::$connection, self::$settings, $this->payload([
            'name' => 'Hacked',
        ]), 'bob'));

        $row = \torrent_select_one(self::$connection, self::$settings, self::HASH);
        $this->assertIsArray($row);
        $this->assertSame('Original', $row['name']);
    }

    public function testOwnerCanUpdateOwnTorrent(): void
    {
        $this->seed('alice');

        $this->assertTrue(\torrent_update(self::$connection, self::$settings, $this->payload([
            'name' => 'AliceEdit',
        ]), 'alice'));

        $row = \torrent_select_one(self::$connection, self::$settings, self::HASH);
        $this->assertIsArray($row);
        $this->assertSame('AliceEdit', $row['name']);
    }

    public function testPreservesDownloadsCounter(): void
    {
        $this->seed(null);
        mysqli_query(
            self::$connection,
            'UPDATE `'.self::$settings['db_prefix'].'torrents` SET `downloads` = 42 WHERE `info_hash` = \''.self::HASH.'\';',
        );

        \torrent_update(self::$connection, self::$settings, $this->payload(), null);

        $row = \torrent_select_one(self::$connection, self::$settings, self::HASH);
        $this->assertIsArray($row);
        $this->assertSame(42, $row['downloads']);
    }
}
