<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/model/peers.select.all.php';
require_once __DIR__.'/../../src/model/peer.insert.php'; // for insertPeer()

class PeersSelectAllTest extends PhoenixTestCase
{
    private const REGISTERED = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const UNREGISTERED = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const PEER_A = '1111111111111111111111111111111111111111';
    private const PEER_B = '2222222222222222222222222222222222222222';

    protected function setUp(): void
    {
        parent::setUp();
        $prefix = self::$settings['db_prefix'];

        // A registered torrent (for the name join) plus a peer in its swarm,
        // and a peer in an unregistered swarm (no torrents row). The
        // unregistered peer is newest, so it must sort first.
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.$prefix.'torrents` (`info_hash`, `name`, `size`, `listed`, `downloads`) '.
            'VALUES (\''.self::REGISTERED.'\', \'__TEST_PeersAll__\', 0, 1, 0);',
        );
        $this->insertPeer(self::REGISTERED, self::PEER_A, 1, 1000);
        $this->insertPeer(self::UNREGISTERED, self::PEER_B, 0, 2000);
    }

    protected function tearDown(): void
    {
        $prefix = self::$settings['db_prefix'];
        foreach ([self::REGISTERED, self::UNREGISTERED] as $hash) {
            mysqli_query(self::$connection, 'DELETE FROM `'.$prefix.'peers` WHERE `info_hash` = \''.$hash.'\';');
        }
        mysqli_query(self::$connection, 'DELETE FROM `'.$prefix.'torrents` WHERE `info_hash` = \''.self::REGISTERED.'\';');
        parent::tearDown();
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>|null
     */
    private function findByPeer(array $rows, string $peer_id): ?array
    {
        foreach ($rows as $row) {
            if ($row['peer_id'] === $peer_id) {
                return $row;
            }
        }

        return null;
    }

    public function testJoinsTorrentNameAndKeepsNullForUnregistered(): void
    {
        $rows = \peers_select_all(self::$connection, self::$settings, 1000, 0);

        $registered = $this->findByPeer($rows, self::PEER_A);
        $this->assertNotNull($registered);
        $this->assertSame(self::REGISTERED, $registered['info_hash']);
        $this->assertSame('__TEST_PeersAll__', $registered['name']);
        $this->assertSame(1, $registered['state']);

        $unregistered = $this->findByPeer($rows, self::PEER_B);
        $this->assertNotNull($unregistered);
        $this->assertSame(self::UNREGISTERED, $unregistered['info_hash']);
        $this->assertNull($unregistered['name']);
    }

    public function testOrdersNewestSeenFirst(): void
    {
        $rows = \peers_select_all(self::$connection, self::$settings, 1000, 0);

        $posA = $posB = null;
        foreach ($rows as $i => $row) {
            if ($row['peer_id'] === self::PEER_A) {
                $posA = $i;
            }
            if ($row['peer_id'] === self::PEER_B) {
                $posB = $i;
            }
        }
        $this->assertNotNull($posA);
        $this->assertNotNull($posB);
        // PEER_B (updated 2000) is newer than PEER_A (updated 1000).
        $this->assertLessThan($posA, $posB);
    }

    public function testLimitCapsRowCount(): void
    {
        // At least the two seeded rows exist, so a limit of 1 returns exactly 1.
        $this->assertCount(1, \peers_select_all(self::$connection, self::$settings, 1, 0));
    }
}
