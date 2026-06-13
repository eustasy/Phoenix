<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class TorrentSetListedTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/torrent.set.listed.php';
    }

    protected function tearDown(): void
    {
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` LIKE \'__TEST_%\';',
        );
    }

    private function insertTorrent(string $infoHash, ?string $user, int $listed): void
    {
        $userSql = $user === null ? 'NULL' : '\''.mysqli_real_escape_string(self::$connection, $user).'\'';
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
            '(`info_hash`, `user`, `name`, `size`, `listed`, `downloads`) VALUES '.
            '(\''.$infoHash.'\', '.$userSql.', \'Name\', 0, '.$listed.', 0);',
        );
    }

    private function fetchListed(string $infoHash): ?int
    {
        $row = mysqli_fetch_assoc(mysqli_query(
            self::$connection,
            'SELECT `listed` FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` = \''.$infoHash.'\';',
        ));

        return is_array($row) ? (int) $row['listed'] : null;
    }

    public function testSetsListedWithMatchingUser(): void
    {
        $this->insertTorrent('__TEST_own__', 'alice', 1);

        $this->assertTrue(\torrent_set_listed(self::$connection, self::$settings, '__TEST_own__', 0, 'alice'));
        $this->assertSame(0, $this->fetchListed('__TEST_own__'));
    }

    public function testUserGuardBlocksAnotherUsersRow(): void
    {
        // A non-matching user guard matches no row, so the value is untouched.
        $this->insertTorrent('__TEST_other__', 'alice', 1);

        \torrent_set_listed(self::$connection, self::$settings, '__TEST_other__', 0, 'bob');
        $this->assertSame(1, $this->fetchListed('__TEST_other__'));
    }

    public function testUserGuardBlocksUnownedRow(): void
    {
        // Null-owner rows can't match a user guard — only the null (admin) guard.
        $this->insertTorrent('__TEST_unowned__', null, 1);

        \torrent_set_listed(self::$connection, self::$settings, '__TEST_unowned__', 0, 'alice');
        $this->assertSame(1, $this->fetchListed('__TEST_unowned__'));
    }

    public function testNullGuardActsOnAnyOwner(): void
    {
        // The admin path passes a null guard: it flips a row regardless of owner.
        $this->insertTorrent('__TEST_admin1__', 'alice', 1);
        $this->insertTorrent('__TEST_admin2__', null, 0);

        $this->assertTrue(\torrent_set_listed(self::$connection, self::$settings, '__TEST_admin1__', 0, null));
        $this->assertTrue(\torrent_set_listed(self::$connection, self::$settings, '__TEST_admin2__', 1, null));
        $this->assertSame(0, $this->fetchListed('__TEST_admin1__'));
        $this->assertSame(1, $this->fetchListed('__TEST_admin2__'));
    }

    public function testIdempotentResetSucceeds(): void
    {
        // Re-listing an already-listed torrent is a no-op that still succeeds.
        $this->insertTorrent('__TEST_idem__', 'alice', 1);

        $this->assertTrue(\torrent_set_listed(self::$connection, self::$settings, '__TEST_idem__', 1, 'alice'));
        $this->assertSame(1, $this->fetchListed('__TEST_idem__'));
    }
}
