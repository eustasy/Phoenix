<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/model/peers.count.php';
require_once __DIR__.'/../../src/model/peer.insert.php'; // for insertPeer()

class PeersCountTest extends PhoenixTestCase
{
    private const HASH = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';

    protected function tearDown(): void
    {
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'peers` WHERE `info_hash` = \''.self::HASH.'\';',
        );
        parent::tearDown();
    }

    public function testCountsActivePeerRows(): void
    {
        // Compare before/after so the global COUNT(*) is robust to any other
        // peer rows present in the shared test table.
        $before = \peers_count(self::$connection, self::$settings);

        $this->insertPeer(self::HASH, str_repeat('1', 40), 1, self::$time);
        $this->insertPeer(self::HASH, str_repeat('2', 40), 0, self::$time);

        $this->assertSame($before + 2, \peers_count(self::$connection, self::$settings));

        // The filtered count must agree with what the filtered listing returns,
        // or the pager trails off into empty pages.
        require_once __DIR__.'/../../src/model/peers.select.all.php';
        foreach ([['', 1], ['', 0]] as [$search, $state]) {
            $this->assertSame(
                count(\peers_select_all(self::$connection, self::$settings, 100000, 0, $search, $state)),
                \peers_count(self::$connection, self::$settings, $search, $state),
            );
        }
    }
}
