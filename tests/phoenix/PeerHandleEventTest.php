<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/functions/peer.handle.event.php';

class PeerHandleEventTest extends PhoenixTestCase
{
    private const HASH = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';
    private const PEER_ID = '3333333333333333333333333333333333333333';

    protected function setUp(): void
    {
        parent::setUp();
        // A torrent row for the downloads counter to target.
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
            '(`info_hash`, `name`, `size`, `listed`, `downloads`) VALUES '.
            '(\''.self::HASH.'\', \'Test\', 1024, 0, 0);',
        );
    }

    protected function tearDown(): void
    {
        foreach (['peers', 'torrents'] as $table) {
            mysqli_query(
                self::$connection,
                'DELETE FROM `'.self::$settings['db_prefix'].$table.'` WHERE `info_hash` = \''.self::HASH.'\';',
            );
        }
        parent::tearDown();
    }

    /**
     * A resolved announce peer in the shape announce_controller hands the
     * function.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function peer(array $overrides = []): array
    {
        return array_merge([
            'info_hash' => self::HASH,
            'peer_id' => self::PEER_ID,
            'ipv4' => '192.0.2.1',
            'ipv6' => false,
            'portv4' => 6881,
            'portv6' => 0,
            'uploaded' => 0,
            'downloaded' => 0,
            'left' => 0,
            'state' => 1,
        ], $overrides);
    }

    private function seedPeer(int $state, int $updated): void
    {
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
            '(`info_hash`, `peer_id`, `compactv4`, `compactv6`, `ipv4`, `portv4`, `portv6`, `left`, `state`, `updated`) VALUES '.
            '(\''.self::HASH.'\', \''.self::PEER_ID.'\', \'\', \'\', \'192.0.2.1\', 6881, 0, 0, '.$state.', '.$updated.');',
        );
    }

    private function fetchPeer(): ?array
    {
        $row = mysqli_fetch_assoc(mysqli_query(
            self::$connection,
            'SELECT * FROM `'.self::$settings['db_prefix'].'peers` '.
            'WHERE `info_hash` = \''.self::HASH.'\' AND `peer_id` = \''.self::PEER_ID.'\';',
        ));

        return $row ?: null;
    }

    private function downloads(): int
    {
        $row = mysqli_fetch_assoc(mysqli_query(
            self::$connection,
            'SELECT `downloads` FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` = \''.self::HASH.'\';',
        ));

        return intval($row['downloads']);
    }

    public function testStoppedReturnsFalseAndDeletesPeer(): void
    {
        $this->seedPeer(1, self::$time);
        $peer = $this->peer();

        $this->assertFalse(\peer_handle_event(self::$connection, self::$settings, self::$time, $peer, 'stopped'));
        $this->assertNull($this->fetchPeer());
    }

    public function testStoppedReturnsFalseWhenNoPeerExists(): void
    {
        $peer = $this->peer();
        // No row to remove — still returns false (empty body) and does not error.
        $this->assertFalse(\peer_handle_event(self::$connection, self::$settings, self::$time, $peer, 'stopped'));
        $this->assertNull($this->fetchPeer());
    }

    public function testCompletedCountsDownloadForcesSeedingAndContinues(): void
    {
        $before = $this->downloads();
        $peer = $this->peer(['state' => 0]); // arrives leeching, completing now

        $this->assertTrue(\peer_handle_event(self::$connection, self::$settings, self::$time, $peer, 'completed'));
        $this->assertSame($before + 1, $this->downloads());
        $this->assertSame(1, $peer['state']); // forced seeding, mutated by reference
        $this->assertNotNull($this->fetchPeer());
    }

    public function testCompletedFromAlreadySeedingPeerDoesNotCount(): void
    {
        $this->seedPeer(1, self::$time); // already recorded as seeding
        $before = $this->downloads();
        $peer = $this->peer();

        $this->assertTrue(\peer_handle_event(self::$connection, self::$settings, self::$time, $peer, 'completed'));
        $this->assertSame($before, $this->downloads()); // not double-counted
    }

    public function testNewPeerIsInsertedAndContinues(): void
    {
        $peer = $this->peer(['state' => 0, 'left' => 100]);
        $this->assertNull($this->fetchPeer());

        $this->assertTrue(\peer_handle_event(self::$connection, self::$settings, self::$time, $peer, null));
        $row = $this->fetchPeer();
        $this->assertNotNull($row);
        $this->assertSame('192.0.2.1', $row['ipv4']);
    }

    public function testUnchangedPeerBumpsTimestamp(): void
    {
        // Same ip/port/state as the request → peer_changed is false → access path.
        $this->seedPeer(1, self::$time - 1000);
        $peer = $this->peer();

        $this->assertTrue(\peer_handle_event(self::$connection, self::$settings, self::$time, $peer, null));
        $row = $this->fetchPeer();
        $this->assertNotNull($row);
        $this->assertSame((string) self::$time, $row['updated']);
    }
}
