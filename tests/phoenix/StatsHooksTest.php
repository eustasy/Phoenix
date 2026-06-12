<?php

declare(strict_types=1);

namespace Phoenix\Tests;

// Exercises the three stat-tracking hooks end-to-end through phoenix_hook(),
// the real dispatcher the announce controller uses. Each firing runs in a
// fresh subprocess: phoenix_hook() include_once's the hook file, so a single
// PHPUnit process could only ever execute a given hook body once — a
// subprocess per firing both sidesteps that and faithfully models the
// announce flow (one process per request). The parent seeds/reads the shared
// TESTING_-prefixed tables; the subprocess bootstraps the same DB and writes
// to them.
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
     * Fire one hook in a fresh subprocess. Bootstraps the test DB (TESTING_
     * prefix), overrides the stats settings, builds $peer, and calls the real
     * phoenix_hook(). Returns the captured process result.
     *
     * @param array<int, string> $events
     * @return array{stdout: string, stderr: string, exit: int}
     */
    private function fire(string $hook, bool $enabled, array $events): array
    {
        $bootstrap = __DIR__.'/../bootstrap.php';
        $peer = [
            'info_hash' => self::HASH,
            'peer_id' => $this->peerIdHex(),
            'ipv4' => '8.8.8.8',
            'ipv6' => false,
        ];

        $script = '<?php '.
            'require '.var_export($bootstrap, true).'; '.
            '$connection = $GLOBALS[\'phoenix_connection\']; '.
            '$settings = $GLOBALS[\'phoenix_settings\']; '.
            '$time = $GLOBALS[\'phoenix_time\']; '.
            '$settings[\'stats_enabled\'] = '.var_export($enabled, true).'; '.
            '$settings[\'stats_events\'] = '.var_export($events, true).'; '.
            '$settings[\'stats_geo\'] = false; '.
            '$settings[\'stats_geo_database\'] = \'\'; '.
            '$peer = '.var_export($peer, true).'; '.
            'require '.var_export(__DIR__.'/../../src/functions/phoenix.hook.php', true).'; '.
            'phoenix_hook('.var_export($hook, true).', $connection, $settings, $time, $peer);';

        return $this->runPhpSubprocess($script);
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
        $r = $this->fire('download.complete', false, ['completed']);
        $this->assertSame(0, $r['exit'], $r['stderr']);
        $this->assertCount(0, $this->eventRows());
    }

    public function testDownloadCompleteEnabledWritesExactlyOneRow(): void
    {
        $this->seedTorrent('owner');
        $r = $this->fire('download.complete', true, ['completed']);
        $this->assertSame(0, $r['exit'], $r['stderr']);

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
        $r = $this->fire('download.complete', true, []);
        $this->assertSame(0, $r['exit'], $r['stderr']);
        $this->assertCount(0, $this->eventRows());
    }

    public function testDownloadCompleteResolvesEmptyUserWhenTorrentAbsent(): void
    {
        // No torrent seeded -> torrent_user returns '' but a row is still logged.
        $r = $this->fire('download.complete', true, ['completed']);
        $this->assertSame(0, $r['exit'], $r['stderr']);

        $rows = $this->eventRows();
        $this->assertCount(1, $rows);
        $this->assertSame('', $rows[0]['user']);
    }

    public function testPeerNewGateClosed(): void
    {
        $this->seedTorrent('owner');
        // 'started' not in the list -> no row (the default posture).
        $r = $this->fire('peer.new', true, ['completed']);
        $this->assertSame(0, $r['exit'], $r['stderr']);
        $this->assertCount(0, $this->eventRows());
    }

    public function testPeerNewGateOpen(): void
    {
        $this->seedTorrent('owner');
        // 'started' listed -> exactly one 'started' row.
        $r = $this->fire('peer.new', true, ['started']);
        $this->assertSame(0, $r['exit'], $r['stderr']);

        $rows = $this->eventRows();
        $this->assertCount(1, $rows);
        $this->assertSame('started', $rows[0]['event']);
        $this->assertSame('owner', $rows[0]['user']);
    }

    public function testPeerStoppedGateClosed(): void
    {
        $this->seedTorrent('owner');
        $r = $this->fire('peer.stopped', true, ['completed']);
        $this->assertSame(0, $r['exit'], $r['stderr']);
        $this->assertCount(0, $this->eventRows());
    }

    public function testPeerStoppedGateOpen(): void
    {
        $this->seedTorrent('owner');
        $r = $this->fire('peer.stopped', true, ['stopped']);
        $this->assertSame(0, $r['exit'], $r['stderr']);

        $rows = $this->eventRows();
        $this->assertCount(1, $rows);
        $this->assertSame('stopped', $rows[0]['event']);
    }

    public function testGlobalGateDisablesPeerHooksRegardlessOfEvents(): void
    {
        $this->seedTorrent('owner');
        $a = $this->fire('peer.new', false, ['started']);
        $b = $this->fire('peer.stopped', false, ['stopped']);
        $this->assertSame(0, $a['exit'], $a['stderr']);
        $this->assertSame(0, $b['exit'], $b['stderr']);
        $this->assertCount(0, $this->eventRows());
    }
}
