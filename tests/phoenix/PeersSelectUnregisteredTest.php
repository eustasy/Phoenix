<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/model/peers.select.unregistered.php';
require_once __DIR__.'/../../src/model/peer.insert.php'; // for insertPeer()

class PeersSelectUnregisteredTest extends PhoenixTestCase
{
    protected function tearDown(): void
    {
        $prefix = self::$settings['db_prefix'];
        mysqli_query(self::$connection, 'DELETE FROM `'.$prefix.'peers` WHERE `info_hash` LIKE \'__TEST_%\';');
        mysqli_query(self::$connection, 'DELETE FROM `'.$prefix.'torrents` WHERE `info_hash` LIKE \'__TEST_%\';');
        parent::tearDown();
    }

    public function testReturnsOnlySwarmsWithoutATorrentRow(): void
    {
        // Unregistered swarm: peers, but no torrents row.
        $this->insertPeer('__TEST_unreg__', '__TEST_pu1__', 1, 1700000000);
        $this->insertPeer('__TEST_unreg__', '__TEST_pu2__', 0, 1700000000);

        // Registered swarm: peers AND a torrents row → must be excluded.
        $this->insertPeer('__TEST_reg__', '__TEST_pr1__', 1, 1700000000);
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'torrents` (`info_hash`, `downloads`) '.
            'VALUES (\'__TEST_reg__\', 0);',
        );

        $swarms = \peers_select_unregistered(self::$connection, self::$settings);
        $byHash = [];
        foreach ($swarms as $swarm) {
            $byHash[$swarm['info_hash']] = $swarm;
        }

        $this->assertArrayHasKey('__TEST_unreg__', $byHash);
        $this->assertArrayNotHasKey('__TEST_reg__', $byHash);

        $this->assertSame(1, $byHash['__TEST_unreg__']['seeders']);
        $this->assertSame(1, $byHash['__TEST_unreg__']['leechers']);
        $this->assertSame(2, $byHash['__TEST_unreg__']['peers']);
    }
}
