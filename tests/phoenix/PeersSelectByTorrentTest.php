<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/model/peers.select.by.torrent.php';
require_once __DIR__.'/../../src/model/peer.insert.php'; // for insertPeer()

class PeersSelectByTorrentTest extends PhoenixTestCase
{
    private const HASH = '__TEST_drill__';

    protected function tearDown(): void
    {
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'peers` WHERE `info_hash` LIKE \'__TEST_%\';',
        );
        parent::tearDown();
    }

    public function testReturnsSwarmPeersNewestFirst(): void
    {
        $this->insertPeer(self::HASH, '__TEST_p_old__', 0, 1700000000);
        $this->insertPeer(self::HASH, '__TEST_p_new__', 1, 1700000100);
        // A peer for another hash must not leak into this swarm.
        $this->insertPeer('__TEST_other__', '__TEST_p_x__', 1, 1700000200);

        $peers = \peers_select_by_torrent(self::$connection, self::$settings, self::HASH);

        $this->assertCount(2, $peers);
        // ORDER BY updated DESC → newest first.
        $this->assertSame('__TEST_p_new__', $peers[0]['peer_id']);
        $this->assertSame(1, $peers[0]['state']);
        $this->assertSame('__TEST_p_old__', $peers[1]['peer_id']);
        $this->assertSame(0, $peers[1]['state']);
        // Numeric columns come back as ints, not mysqli's strings.
        $this->assertIsInt($peers[0]['updated']);
        $this->assertIsInt($peers[0]['uploaded']);
        $this->assertIsInt($peers[0]['portv4']);
    }

    public function testReturnsEmptyForSwarmWithNoPeers(): void
    {
        $this->assertSame([], \peers_select_by_torrent(self::$connection, self::$settings, '__TEST_nope__'));
    }
}
