<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class TorrentUserTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/torrent.user.php';
    }

    protected function tearDown(): void
    {
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` LIKE \'__TEST_%\';',
        );
    }

    private function seedTorrent(string $info_hash, ?string $user): void
    {
        mysqli_execute_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'torrents` (`user`, `info_hash`, `listed`) VALUES (?, ?, 0);',
            [$user, $info_hash],
        );
    }

    public function testReturnsOwner(): void
    {
        $this->seedTorrent('__TEST_USER_1__', 'alice');
        $this->assertSame('alice', torrent_user(self::$connection, self::$settings, '__TEST_USER_1__'));
    }

    public function testReturnsEmptyForNullOwner(): void
    {
        $this->seedTorrent('__TEST_USER_2__', null);
        $this->assertSame('', torrent_user(self::$connection, self::$settings, '__TEST_USER_2__'));
    }

    public function testReturnsEmptyForAbsentRow(): void
    {
        $this->assertSame('', torrent_user(self::$connection, self::$settings, '__TEST_USER_ABSENT__'));
    }
}
