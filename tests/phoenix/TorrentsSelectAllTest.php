<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class TorrentsSelectAllTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/torrents.select.all.php';
    }

    protected function tearDown(): void
    {
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` LIKE \'__TEST_%\';',
        );
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'peers` WHERE `info_hash` LIKE \'__TEST_%\';',
        );
    }

    /**
     * @param array<string, string|null> $meta Optional meta columns: filename, files, trackers, webseeds.
     */
    private function insertTorrent(
        string $infoHash,
        string $name,
        int $size,
        int $listed,
        int $downloads,
        ?string $user = null,
        array $meta = [],
    ): void {
        $userSql = $user === null ? 'NULL' : '\''.mysqli_real_escape_string(self::$connection, $user).'\'';
        $filename = isset($meta['filename']) ? '\''.mysqli_real_escape_string(self::$connection, $meta['filename']).'\'' : 'NULL';
        $files = isset($meta['files']) ? '\''.mysqli_real_escape_string(self::$connection, $meta['files']).'\'' : 'NULL';
        $trackers = isset($meta['trackers']) ? '\''.mysqli_real_escape_string(self::$connection, $meta['trackers']).'\'' : 'NULL';
        $webseeds = isset($meta['webseeds']) ? '\''.mysqli_real_escape_string(self::$connection, $meta['webseeds']).'\'' : 'NULL';

        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
            '(`info_hash`, `user`, `name`, `size`, `listed`, `downloads`, `filename`, `files`, `trackers`, `webseeds`) VALUES '.
            '(\''.$infoHash.'\', '.$userSql.', \''.$name.'\', '.$size.', '.$listed.', '.$downloads.', '.$filename.', '.$files.', '.$trackers.', '.$webseeds.');',
        );
    }

    private function insertPeerRow(string $infoHash, string $peerId, int $state): void
    {
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
            '(`info_hash`, `peer_id`, `compactv4`, `compactv6`, `portv4`, `portv6`, `state`, `updated`) VALUES '.
            '(\''.$infoHash.'\', \''.$peerId.'\', \'\', \'\', 0, 0, '.$state.', '.self::$time.');',
        );
    }

    /** @return array<string, mixed> Row keyed result for the given info_hash, or [] if absent. */
    private function rowFor(string $infoHash): array
    {
        foreach (\torrents_select_all(self::$connection, self::$settings) as $row) {
            if ($row['info_hash'] === $infoHash) {
                return $row;
            }
        }

        return [];
    }

    public function testReturnsEmptyArrayWhenNoTorrents(): void
    {
        $this->assertSame([], \torrents_select_all(self::$connection, self::$settings));
    }

    public function testIncludesUnlistedTorrents(): void
    {
        // The distinguishing behaviour from torrents_select_listed(): an
        // unlisted row IS returned, carrying its listed=0 flag.
        $this->insertTorrent('__TEST_unlisted__', 'Hidden', 100, 0, 0, 'owner');

        $row = $this->rowFor('__TEST_unlisted__');
        $this->assertNotEmpty($row);
        $this->assertSame(0, $row['listed']);
        $this->assertSame('owner', $row['user']);
    }

    public function testReturnsTorrentWithComputedFields(): void
    {
        // Listed torrent with no peers — exercises the no-peer
        // IFNULL(SUM(...),0) branch and the peers/traffic derivations, plus the
        // new user/listed columns. Meta NULL -> normalized to null.
        $this->insertTorrent('__TEST_solo__', 'Solo', 1024, 1, 5, 'alice');

        $this->assertSame([
            'info_hash' => '__TEST_solo__',
            'user' => 'alice',
            'name' => 'Solo',
            'size' => 1024,
            'listed' => 1,
            'downloads' => 5,
            'seeders' => 0,
            'leechers' => 0,
            'peers' => 0,
            'traffic' => 1024 * 5,
            'filename' => null,
            'files' => null,
            'trackers' => null,
            'webseeds' => null,
        ], $this->rowFor('__TEST_solo__'));
    }

    public function testNullUserNormalizesToNull(): void
    {
        // Announce-created rows carry no user; the column is NULL and stays null.
        $this->insertTorrent('__TEST_no_user__', 'Orphan', 0, 1, 0, null);

        $row = $this->rowFor('__TEST_no_user__');
        $this->assertNotEmpty($row);
        $this->assertNull($row['user']);
    }

    public function testCountsSeedersAndLeechersFromPeers(): void
    {
        $this->insertTorrent('__TEST_swarm__', 'Swarm', 2048, 1, 10, 'bob');
        $this->insertPeerRow('__TEST_swarm__', '__TEST_peer_seed_1__', 1);
        $this->insertPeerRow('__TEST_swarm__', '__TEST_peer_seed_2__', 1);
        $this->insertPeerRow('__TEST_swarm__', '__TEST_peer_leech_1__', 0);

        $row = $this->rowFor('__TEST_swarm__');
        $this->assertSame(2, $row['seeders']);
        $this->assertSame(1, $row['leechers']);
        $this->assertSame(3, $row['peers']);
        $this->assertSame(2048 * 10, $row['traffic']);
    }

    public function testMetaDecodedAndSplit(): void
    {
        $files = json_encode([
            ['path' => 'dir/file.mkv', 'length' => 1234567],
            ['path' => 'dir/sub.srt', 'length' => 890],
        ]);
        $this->assertIsString($files);

        $this->insertTorrent('__TEST_meta__', 'WithMeta', 0, 0, 0, 'carol', [
            'filename' => 'my.file.mkv',
            'files' => $files,
            'trackers' => "https://tracker1.example/announce\nhttps://tracker2.example/announce\n",
            'webseeds' => "https://seed1.example/\nhttps://seed2.example/",
        ]);

        $row = $this->rowFor('__TEST_meta__');
        $this->assertSame('my.file.mkv', $row['filename']);
        $this->assertSame([
            ['path' => 'dir/file.mkv', 'length' => 1234567],
            ['path' => 'dir/sub.srt',  'length' => 890],
        ], $row['files']);
        $this->assertSame(
            ['https://tracker1.example/announce', 'https://tracker2.example/announce'],
            $row['trackers'],
        );
        $this->assertSame(['https://seed1.example/', 'https://seed2.example/'], $row['webseeds']);
    }

    public function testInvalidJsonFilesBecomesNull(): void
    {
        $this->insertTorrent('__TEST_badjson__', 'BadJson', 0, 1, 0, 'dave', [
            'files' => 'not-valid-json',
        ]);

        $this->assertNull($this->rowFor('__TEST_badjson__')['files']);
    }

    public function testOrdersByName(): void
    {
        // SQL has ORDER BY t.name; insert out of order — and mix listed/unlisted
        // so the ordering isn't accidentally a side effect of insertion order.
        $this->insertTorrent('__TEST_charlie__', 'Charlie', 0, 1, 0, 'u');
        $this->insertTorrent('__TEST_alpha__', 'Alpha', 0, 0, 0, 'u');
        $this->insertTorrent('__TEST_bravo__', 'Bravo', 0, 1, 0, 'u');

        $names = array_column(
            \torrents_select_all(self::$connection, self::$settings),
            'name',
        );
        // Only our seeded rows are asserted on (the table may hold others in a
        // shared test DB); check relative ordering.
        $ours = array_values(array_filter($names, static fn ($n) => in_array($n, ['Alpha', 'Bravo', 'Charlie'], true)));
        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $ours);
    }

}
