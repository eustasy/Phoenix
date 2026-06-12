<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class TorrentsSelectListedTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/torrents.select.listed.php';
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
        array $meta = [],
    ): void {
        $filename = isset($meta['filename']) ? '\''.mysqli_real_escape_string(self::$connection, $meta['filename']).'\'' : 'NULL';
        $files = isset($meta['files']) ? '\''.mysqli_real_escape_string(self::$connection, $meta['files']).'\'' : 'NULL';
        $trackers = isset($meta['trackers']) ? '\''.mysqli_real_escape_string(self::$connection, $meta['trackers']).'\'' : 'NULL';
        $webseeds = isset($meta['webseeds']) ? '\''.mysqli_real_escape_string(self::$connection, $meta['webseeds']).'\'' : 'NULL';

        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
            '(`info_hash`, `name`, `size`, `listed`, `downloads`, `filename`, `files`, `trackers`, `webseeds`) VALUES '.
            '(\''.$infoHash.'\', \''.$name.'\', '.$size.', '.$listed.', '.$downloads.', '.$filename.', '.$files.', '.$trackers.', '.$webseeds.');',
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

    public function testReturnsEmptyArrayWhenNoTorrents(): void
    {
        $this->assertSame([], \torrents_select_listed(self::$connection, self::$settings));
    }

    public function testIgnoresUnlistedTorrents(): void
    {
        // listed=0 rows should not appear in the public index even when they
        // exist in the table (e.g. open-tracker torrents the operator hasn't
        // chosen to publish).
        $this->insertTorrent('__TEST_unlisted__', 'Hidden', 100, 0, 0);
        $this->assertSame([], \torrents_select_listed(self::$connection, self::$settings));
    }

    public function testReturnsListedTorrentsWithComputedFields(): void
    {
        // Single listed torrent with no peers — exercises the no-peer
        // IFNULL(SUM(...),0) branch and the `peers`/`traffic` derivations.
        // Meta columns are NULL so normalized to null.
        $this->insertTorrent('__TEST_listed__', 'Solo', 1024, 1, 5);

        $result = \torrents_select_listed(self::$connection, self::$settings);

        $this->assertCount(1, $result);
        $this->assertSame([
            'info_hash' => '__TEST_listed__',
            'name' => 'Solo',
            'size' => 1024,
            'downloads' => 5,
            'seeders' => 0,
            'leechers' => 0,
            'peers' => 0,
            'traffic' => 1024 * 5,
            'filename' => null,
            'files' => null,
            'trackers' => null,
            'webseeds' => null,
        ], $result[0]);
    }

    public function testMetaNullColumnsNormalizeToNull(): void
    {
        // When the DB columns are NULL, normalized meta must all be null.
        $this->insertTorrent('__TEST_meta_null__', 'NullMeta', 0, 1, 0);

        $result = \torrents_select_listed(self::$connection, self::$settings);
        $this->assertCount(1, $result);
        $this->assertNull($result[0]['filename']);
        $this->assertNull($result[0]['files']);
        $this->assertNull($result[0]['trackers']);
        $this->assertNull($result[0]['webseeds']);
    }

    public function testMetaFilenamePassedThrough(): void
    {
        $this->insertTorrent('__TEST_meta_fn__', 'WithFilename', 0, 1, 0, [
            'filename' => 'my.file.mkv',
        ]);

        $result = \torrents_select_listed(self::$connection, self::$settings);
        $this->assertCount(1, $result);
        $this->assertSame('my.file.mkv', $result[0]['filename']);
    }

    public function testMetaFilesDecodedToList(): void
    {
        $json = json_encode([
            ['path' => 'dir/file.mkv', 'length' => 1234567],
            ['path' => 'dir/sub.srt', 'length' => 890],
        ]);
        $this->assertIsString($json);

        $this->insertTorrent('__TEST_meta_files__', 'WithFiles', 0, 1, 0, [
            'files' => $json,
        ]);

        $result = \torrents_select_listed(self::$connection, self::$settings);
        $this->assertCount(1, $result);
        $this->assertSame([
            ['path' => 'dir/file.mkv', 'length' => 1234567],
            ['path' => 'dir/sub.srt',  'length' => 890],
        ], $result[0]['files']);
    }

    public function testMetaInvalidJsonFilesBecomesNull(): void
    {
        $this->insertTorrent('__TEST_meta_badjson__', 'BadJson', 0, 1, 0, [
            'files' => 'not-valid-json',
        ]);

        $result = \torrents_select_listed(self::$connection, self::$settings);
        $this->assertCount(1, $result);
        $this->assertNull($result[0]['files']);
    }

    public function testMetaTrackersAndWebseedsSplitOnNewlines(): void
    {
        $this->insertTorrent('__TEST_meta_urls__', 'WithUrls', 0, 1, 0, [
            'trackers' => "https://tracker1.example/announce\nhttps://tracker2.example/announce\n",
            'webseeds' => "https://seed1.example/\nhttps://seed2.example/",
        ]);

        $result = \torrents_select_listed(self::$connection, self::$settings);
        $this->assertCount(1, $result);
        $this->assertSame(
            ['https://tracker1.example/announce', 'https://tracker2.example/announce'],
            $result[0]['trackers'],
        );
        $this->assertSame(
            ['https://seed1.example/', 'https://seed2.example/'],
            $result[0]['webseeds'],
        );
    }

    public function testCountsSeedersAndLeechersFromPeers(): void
    {
        // state=1 → seeder, state=0 → leecher. peers = seeders + leechers,
        // so we can sanity-check the join arithmetic in one pass.
        $this->insertTorrent('__TEST_swarm__', 'Swarm', 2048, 1, 10);
        $this->insertPeerRow('__TEST_swarm__', '__TEST_peer_seed_1__', 1);
        $this->insertPeerRow('__TEST_swarm__', '__TEST_peer_seed_2__', 1);
        $this->insertPeerRow('__TEST_swarm__', '__TEST_peer_leech_1__', 0);

        $result = \torrents_select_listed(self::$connection, self::$settings);

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]['seeders']);
        $this->assertSame(1, $result[0]['leechers']);
        $this->assertSame(3, $result[0]['peers']);
        $this->assertSame(2048 * 10, $result[0]['traffic']);
    }

    public function testOrdersByName(): void
    {
        // SQL has ORDER BY t.name; insert out of order so an unsorted
        // fetch would visibly fail this assertion.
        $this->insertTorrent('__TEST_charlie__', 'Charlie', 0, 1, 0);
        $this->insertTorrent('__TEST_alpha__', 'Alpha', 0, 1, 0);
        $this->insertTorrent('__TEST_bravo__', 'Bravo', 0, 1, 0);

        $names = array_column(
            \torrents_select_listed(self::$connection, self::$settings),
            'name',
        );
        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $names);
    }

}
