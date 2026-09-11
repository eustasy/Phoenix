<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class PeersTopTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/peers.top.php';
    }

    protected function tearDown(): void
    {
        $prefix = self::$settings['db_prefix'];
        mysqli_query(self::$connection, 'DELETE FROM `'.$prefix.'peers` WHERE `info_hash` LIKE \'__TEST_%\';');
        mysqli_query(self::$connection, 'DELETE FROM `'.$prefix.'torrents` WHERE `info_hash` LIKE \'__TEST_%\';');
        parent::tearDown();
    }

    private function torrent(string $hash, string $name): void
    {
        mysqli_execute_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
            '(`info_hash`, `name`, `size`, `listed`, `downloads`) VALUES (?, ?, 0, 1, 0);',
            [$hash, $name],
        );
    }

    private function peer(string $hash, string $ip, int $state, int $uploaded, int $downloaded): void
    {
        mysqli_execute_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
            '(`info_hash`, `peer_id`, `compactv4`, `compactv6`, `ipv4`, `portv4`, `portv6`, '.
            '`uploaded`, `downloaded`, `left`, `state`, `updated`) '.
            'VALUES (?, ?, \'\', \'\', ?, 51413, 0, ?, ?, 0, ?, ?);',
            [$hash, bin2hex(substr(str_pad($ip, 20, 'x'), 0, 20)), $ip, $uploaded, $downloaded, $state, time()],
        );
    }

    /** @param list<array<string, mixed>> $rows */
    private function ours(array $rows): array
    {
        return array_values(array_filter(
            array_column($rows, 'address'),
            static fn (string $a): bool => str_starts_with($a, '10.'),
        ));
    }

    public function testSeedersMeasureRanksSeedersByBytesUploaded(): void
    {
        $this->torrent('__TEST_pt__', '__TEST_Name__');
        $this->peer('__TEST_pt__', '10.0.0.1', 1, 5000000000, 0);
        $this->peer('__TEST_pt__', '10.0.0.2', 1, 9000000000, 0);
        // A leecher must not appear under the seeders measure.
        $this->peer('__TEST_pt__', '10.0.0.3', 0, 7000000000, 0);

        $rows = \peers_top(self::$connection, self::$settings, 'seeders', 50);

        $this->assertSame(['10.0.0.2', '10.0.0.1'], $this->ours($rows));
    }

    public function testLeechersMeasureRanksLeechersByBytesDownloaded(): void
    {
        $this->torrent('__TEST_pt__', '__TEST_Name__');
        $this->peer('__TEST_pt__', '10.0.0.1', 0, 0, 1000000000);
        $this->peer('__TEST_pt__', '10.0.0.2', 0, 0, 4000000000);

        $this->assertSame(
            ['10.0.0.2', '10.0.0.1'],
            $this->ours(\peers_top(self::$connection, self::$settings, 'leechers', 50)),
        );
    }

    public function testTrafficMeasureIgnoresStateSoABusyLeecherCounts(): void
    {
        // A leecher that is also serving belongs in a chart of who is moving
        // the most data; the state-filtered measures would hide it.
        $this->torrent('__TEST_pt__', '__TEST_Name__');
        $this->peer('__TEST_pt__', '10.0.0.1', 1, 1000000000, 0);
        $this->peer('__TEST_pt__', '10.0.0.2', 0, 9000000000, 50);

        $this->assertSame(
            ['10.0.0.2', '10.0.0.1'],
            $this->ours(\peers_top(self::$connection, self::$settings, 'traffic', 50)),
        );
    }

    public function testCarriesBothCountersAndTheTorrentName(): void
    {
        // A card shows the ratio, so neither counter may be dropped.
        $this->torrent('__TEST_pt__', '__TEST_Name__');
        $this->peer('__TEST_pt__', '10.0.0.1', 1, 5000000000, 2500000000);

        $rows = \peers_top(self::$connection, self::$settings, 'seeders', 50);
        $row = null;
        foreach ($rows as $candidate) {
            if ($candidate['address'] === '10.0.0.1') {
                $row = $candidate;
            }
        }

        $this->assertNotNull($row);
        $this->assertSame(5000000000, $row['uploaded']);
        $this->assertSame(2500000000, $row['downloaded']);
        $this->assertSame('__TEST_Name__', $row['name']);
        $this->assertSame(1, $row['state']);
    }

    public function testPeerMovingNoBytesIsExcluded(): void
    {
        $this->torrent('__TEST_pt__', '__TEST_Name__');
        $this->peer('__TEST_pt__', '10.0.0.9', 1, 0, 0);

        $this->assertSame([], $this->ours(\peers_top(self::$connection, self::$settings, 'seeders', 50)));
    }

    public function testUnknownMeasureFallsBackToSeeders(): void
    {
        $this->torrent('__TEST_pt__', '__TEST_Name__');
        $this->peer('__TEST_pt__', '10.0.0.1', 1, 5000000000, 0);

        $this->assertSame(
            $this->ours(\peers_top(self::$connection, self::$settings, 'seeders', 50)),
            $this->ours(\peers_top(self::$connection, self::$settings, 'nonsense', 50)),
        );
    }
}
