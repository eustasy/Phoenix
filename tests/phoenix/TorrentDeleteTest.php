<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class TorrentDeleteTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/torrent.delete.php';
    }

    protected function tearDown(): void
    {
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` LIKE \'__TEST_%\';',
        );
    }

    private function insertTorrent(string $infoHash, ?string $user): void
    {
        $userSql = $user === null ? 'NULL' : '\''.mysqli_real_escape_string(self::$connection, $user).'\'';
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
            '(`info_hash`, `user`, `name`, `size`, `listed`, `downloads`) VALUES '.
            '(\''.$infoHash.'\', '.$userSql.', \'Name\', 0, 1, 0);',
        );
    }

    private function exists(string $infoHash): bool
    {
        $row = mysqli_fetch_assoc(mysqli_query(
            self::$connection,
            'SELECT 1 AS `x` FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` = \''.$infoHash.'\';',
        ));

        return is_array($row);
    }

    public function testDeletesWithMatchingUser(): void
    {
        $this->insertTorrent('__TEST_own__', 'alice');

        $this->assertTrue(\torrent_delete(self::$connection, self::$settings, '__TEST_own__', 'alice'));
        $this->assertFalse($this->exists('__TEST_own__'));
    }

    public function testUserGuardBlocksAnotherUsersRow(): void
    {
        $this->insertTorrent('__TEST_other__', 'alice');

        \torrent_delete(self::$connection, self::$settings, '__TEST_other__', 'bob');
        $this->assertTrue($this->exists('__TEST_other__'));
    }

    public function testUserGuardBlocksUnownedRow(): void
    {
        $this->insertTorrent('__TEST_unowned__', null);

        \torrent_delete(self::$connection, self::$settings, '__TEST_unowned__', 'alice');
        $this->assertTrue($this->exists('__TEST_unowned__'));
    }

    public function testNullGuardDeletesAnyOwner(): void
    {
        // The admin path passes a null guard: it removes a row regardless of owner.
        $this->insertTorrent('__TEST_admin1__', 'alice');
        $this->insertTorrent('__TEST_admin2__', null);

        $this->assertTrue(\torrent_delete(self::$connection, self::$settings, '__TEST_admin1__', null));
        $this->assertTrue(\torrent_delete(self::$connection, self::$settings, '__TEST_admin2__', null));
        $this->assertFalse($this->exists('__TEST_admin1__'));
        $this->assertFalse($this->exists('__TEST_admin2__'));
    }

    public function testDeletingMissingRowStillSucceeds(): void
    {
        // No row matches; the statement executes cleanly (nothing to remove).
        $this->assertTrue(\torrent_delete(self::$connection, self::$settings, '__TEST_absent__', null));
    }
}
