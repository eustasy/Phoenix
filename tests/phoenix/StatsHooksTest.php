<?php

declare(strict_types=1);

namespace Phoenix\Tests;

// Exercises the three stat-tracking hooks end-to-end through phoenix_hook(),
// the real dispatcher the announce controller uses. phoenix_hook() uses a plain
// include (not include_once — see its own comment: FPM workers fire each hook
// per request, many requests per process), so a hook body re-runs on every call
// and can be fired repeatedly in one process. Firing in-process — rather than in
// a subprocess — keeps the firings under the coverage
// driver, so the opt-in insert path (which only runs with stats_enabled and the
// event opted into stats_events) is recorded as covered, not just exercised.
// The real one-process-per-request dispatch is still covered by the announce
// controller tests and the smoke suite.
class StatsHooksTest extends PhoenixTestCase
{
    private const HASH = '__TEST_HOOK_1__';

    protected function tearDown(): void
    {
        foreach (['events', 'torrents'] as $table) {
            mysqli_query(
                self::$connection,
                'DELETE FROM `'.self::$settings['db_prefix'].$table.'` WHERE `info_hash` LIKE \'__TEST_%\';',
            );
        }
    }

    // A real Azureus-style peer_id ('-qB4620-........') in the 40-char hex form
    // the codebase passes around. Detection must yield 'qBittorrent 4.6.2.0'.
    private function peerIdHex(): string
    {
        return bin2hex('-qB4620-'.str_repeat('x', 12));
    }

    private function seedTorrent(string $user): void
    {
        mysqli_execute_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'torrents` (`user`, `info_hash`, `listed`) VALUES (?, ?, 0);',
            [$user, self::HASH],
        );
    }

    /**
     * Fire one hook in-process through the real phoenix_hook(), with the stats
     * settings overridden for this firing and a fixed $peer. The hook writes to
     * the shared TESTING_-prefixed events table, which the parent then reads.
     *
     * @param array<int, string> $events
     */
    private function fire(string $hook, bool $enabled, array $events): void
    {
        require_once __DIR__.'/../../src/functions/phoenix.hook.php';

        $settings = self::$settings;
        $settings['stats_enabled'] = $enabled;
        $settings['stats_events'] = $events;
        $settings['stats_geo'] = false;
        $settings['stats_geo_database'] = '';

        $peer = [
            'info_hash' => self::HASH,
            'peer_id' => $this->peerIdHex(),
            'ipv4' => '8.8.8.8',
            'ipv6' => false,
        ];

        phoenix_hook($hook, self::$connection, $settings, self::$time, $peer);
    }

    /** @return array<int, array<string, string|null>> */
    private function eventRows(): array
    {
        $result = mysqli_query(
            self::$connection,
            'SELECT `event`, `client`, `user`, `country`, `continent` '.
            'FROM `'.self::$settings['db_prefix'].'events` '.
            'WHERE `info_hash` = \''.self::HASH.'\' ORDER BY `id`;',
        );
        $this->assertInstanceOf(\mysqli_result::class, $result);

        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }

    public function testDownloadCompleteDisabledWritesNoRow(): void
    {
        $this->seedTorrent('owner');
        $this->fire('download.complete', false, ['completed']);
        $this->assertCount(0, $this->eventRows());
    }

    public function testDownloadCompleteEnabledWritesExactlyOneRow(): void
    {
        $this->seedTorrent('owner');
        $this->fire('download.complete', true, ['completed']);

        $rows = $this->eventRows();
        $this->assertCount(1, $rows);
        $this->assertSame('completed', $rows[0]['event']);
        $this->assertSame('qBittorrent 4.6.2.0', $rows[0]['client']);
        $this->assertSame('owner', $rows[0]['user']);
        // Geo is disabled, so the codes stay empty.
        $this->assertSame('', $rows[0]['country']);
        $this->assertSame('', $rows[0]['continent']);
    }

    public function testDownloadCompleteEnabledButEmptyEventsWritesNoRow(): void
    {
        $this->seedTorrent('owner');
        $this->fire('download.complete', true, []);
        $this->assertCount(0, $this->eventRows());
    }

    public function testDownloadCompleteResolvesEmptyUserWhenTorrentAbsent(): void
    {
        // No torrent seeded -> torrent_user returns '' but a row is still logged.
        $this->fire('download.complete', true, ['completed']);

        $rows = $this->eventRows();
        $this->assertCount(1, $rows);
        $this->assertSame('', $rows[0]['user']);
    }

    public function testPeerNewGateClosed(): void
    {
        $this->seedTorrent('owner');
        // 'started' not in the list -> no row (the default posture).
        $this->fire('peer.new', true, ['completed']);
        $this->assertCount(0, $this->eventRows());
    }

    public function testPeerNewGateOpenLogsFullRow(): void
    {
        $this->seedTorrent('owner');
        // 'started' listed -> exactly one fully-populated 'started' row.
        $this->fire('peer.new', true, ['started']);

        $rows = $this->eventRows();
        $this->assertCount(1, $rows);
        $this->assertSame('started', $rows[0]['event']);
        $this->assertSame('qBittorrent 4.6.2.0', $rows[0]['client']);
        $this->assertSame('owner', $rows[0]['user']);
        $this->assertSame('', $rows[0]['country']);
        $this->assertSame('', $rows[0]['continent']);
    }

    public function testPeerNewResolvesEmptyUserWhenTorrentAbsent(): void
    {
        // No torrent seeded -> torrent_user returns '' but the row still logs.
        $this->fire('peer.new', true, ['started']);

        $rows = $this->eventRows();
        $this->assertCount(1, $rows);
        $this->assertSame('', $rows[0]['user']);
    }

    public function testPeerStoppedGateClosed(): void
    {
        $this->seedTorrent('owner');
        $this->fire('peer.stopped', true, ['completed']);
        $this->assertCount(0, $this->eventRows());
    }

    public function testPeerStoppedGateOpenLogsFullRow(): void
    {
        $this->seedTorrent('owner');
        $this->fire('peer.stopped', true, ['stopped']);

        $rows = $this->eventRows();
        $this->assertCount(1, $rows);
        $this->assertSame('stopped', $rows[0]['event']);
        $this->assertSame('qBittorrent 4.6.2.0', $rows[0]['client']);
        $this->assertSame('owner', $rows[0]['user']);
        $this->assertSame('', $rows[0]['country']);
        $this->assertSame('', $rows[0]['continent']);
    }

    public function testGlobalGateDisablesPeerHooksRegardlessOfEvents(): void
    {
        $this->seedTorrent('owner');
        $this->fire('peer.new', false, ['started']);
        $this->fire('peer.stopped', false, ['stopped']);
        $this->assertCount(0, $this->eventRows());
    }
}
