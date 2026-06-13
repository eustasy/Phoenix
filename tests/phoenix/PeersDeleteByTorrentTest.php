<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class PeersDeleteByTorrentTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/peers.delete.by.torrent.php';
    }

    protected function tearDown(): void
    {
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'peers` WHERE `info_hash` LIKE \'__TEST_%\';',
        );
    }

    private function insertPeer(string $infoHash, string $peerId): void
    {
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
            '(`info_hash`, `peer_id`, `compactv4`, `compactv6`, `portv4`, `portv6`, `state`, `updated`) VALUES '.
            '(\''.$infoHash.'\', \''.$peerId.'\', \'\', \'\', 0, 0, 0, '.self::$time.');',
        );
    }

    private function peerCount(string $infoHash): int
    {
        $row = mysqli_fetch_assoc(mysqli_query(
            self::$connection,
            'SELECT COUNT(*) AS `c` FROM `'.self::$settings['db_prefix'].'peers` WHERE `info_hash` = \''.$infoHash.'\';',
        ));

        return is_array($row) ? (int) $row['c'] : 0;
    }

    public function testRemovesOnlyTheTargetTorrentsPeers(): void
    {
        $this->insertPeer('__TEST_keep__', '__TEST_peer_k1__');
        $this->insertPeer('__TEST_drop__', '__TEST_peer_d1__');
        $this->insertPeer('__TEST_drop__', '__TEST_peer_d2__');

        $this->assertTrue(\peers_delete_by_torrent(self::$connection, self::$settings, '__TEST_drop__'));

        // The target swarm is gone; the unrelated torrent's peer survives.
        $this->assertSame(0, $this->peerCount('__TEST_drop__'));
        $this->assertSame(1, $this->peerCount('__TEST_keep__'));
    }

    public function testSucceedsWhenNoPeersExist(): void
    {
        $this->assertTrue(\peers_delete_by_torrent(self::$connection, self::$settings, '__TEST_none__'));
    }
}
