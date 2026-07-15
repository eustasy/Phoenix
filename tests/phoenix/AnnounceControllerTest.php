<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/controller/announce.php';

class AnnounceControllerTest extends PhoenixTestCase
{
    private const CONTROLLER_PATH = __DIR__.'/../../src/controller/announce.php';

    // 40-char hex info_hash + peer_id (sanitize_tracker_params runs them
    // through maybe_binary_to_hex, which accepts 40-char hex unchanged).
    private const HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const PEER_ID_A = '1111111111111111111111111111111111111111';
    private const PEER_ID_B = '2222222222222222222222222222222222222222';

    private int $errorReporting;

    /** @var array<string, mixed> */
    private array $getBackup;

    /** @var array<string, mixed> */
    private array $serverBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->errorReporting = error_reporting();
        $this->getBackup = $_GET;
        $this->serverBackup = $_SERVER;
        error_reporting(0);

        // The torrent the announce will register against.
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
            '(`info_hash`, `name`, `size`, `listed`, `downloads`) VALUES '.
            '(\''.self::HASH.'\', \'Test\', 1024, 0, 0);',
        );
    }

    protected function tearDown(): void
    {
        error_reporting($this->errorReporting);
        $_GET = $this->getBackup;
        $_SERVER = $this->serverBackup;
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` = \''.self::HASH.'\';',
        );
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'peers` WHERE `info_hash` = \''.self::HASH.'\';',
        );
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'events` WHERE `info_hash` = \''.self::HASH.'\';',
        );
        parent::tearDown();
    }

    /**
     * Build a valid announce request. $overrides replaces top-level $_GET
     * keys; pass null to drop a key entirely. Sets QUERY_STRING and
     * REMOTE_ADDR consistently.
     *
     * @param array<string, string|int|null> $overrides
     */
    private function makeRequest(array $overrides = []): void
    {
        $base = [
            'info_hash' => self::HASH,
            'peer_id' => self::PEER_ID_A,
            'port' => '6881',
            'left' => '0',  // seeding
            'compact' => '0',  // non-compact so we can read peer_id back
        ];
        foreach ($overrides as $k => $v) {
            if ($v === null) {
                unset($base[$k]);
            } else {
                $base[$k] = $v;
            }
        }
        $_GET = $base;
        $_SERVER['QUERY_STRING'] = http_build_query($base);
        $_SERVER['REMOTE_ADDR'] = '192.0.2.1';
    }

    private function settingsForTest(int $cleanWithRequests = 0): array
    {
        // clean_with_cron=true short-circuits the cleanup branch entirely,
        // keeping the announce flow deterministic (no incidental table
        // mutations during the response build).
        $s = self::$settings;
        $s['clean_with_cron'] = true;
        $s['clean_request_percent'] = $cleanWithRequests;

        return $s;
    }

    private function fetchPeer(string $peerId): ?array
    {
        $result = mysqli_query(
            self::$connection,
            'SELECT * FROM `'.self::$settings['db_prefix'].'peers` '.
            'WHERE `info_hash` = \''.self::HASH.'\' AND `peer_id` = \''.$peerId.'\';',
        );
        if (! $result) {
            return null;
        }
        $row = mysqli_fetch_assoc($result);

        return $row ?: null;
    }

    private function fetchTorrentDownloads(): int
    {
        $result = mysqli_query(
            self::$connection,
            'SELECT `downloads` FROM `'.self::$settings['db_prefix'].'torrents` '.
            'WHERE `info_hash` = \''.self::HASH.'\';',
        );
        $row = mysqli_fetch_assoc($result);

        return intval($row['downloads']);
    }

    private function fetchEventCount(string $event): int
    {
        $result = mysqli_query(
            self::$connection,
            'SELECT COUNT(*) AS `c` FROM `'.self::$settings['db_prefix'].'events` '.
            'WHERE `info_hash` = \''.self::HASH.'\' AND `event` = \''.$event.'\';',
        );
        $row = mysqli_fetch_assoc($result);

        return intval($row['c']);
    }

    ////	Format dispatch

    public function testRendersBencodeByDefault(): void
    {
        $this->makeRequest();
        $body = \announce_controller(self::$connection, $this->settingsForTest(), self::$time, [self::HASH]);

        $this->assertIsString($body);
        $this->assertStringStartsWith('d', $body);
        $this->assertStringContainsString('8:completei', $body);
        $this->assertStringContainsString('10:incompletei', $body);
        $this->assertStringContainsString('8:intervali'.self::$settings['announce_rec_interval'].'e', $body);
        $this->assertStringContainsString('12:min intervali'.self::$settings['announce_min_interval'].'e', $body);
        $this->assertStringContainsString('5:peers', $body);
    }

    public function testRendersXmlWhenXmlFlagSet(): void
    {
        $this->makeRequest(['xml' => '1']);
        $body = \announce_controller(self::$connection, $this->settingsForTest(), self::$time, [self::HASH]);

        $this->assertStringStartsWith('<?xml', $body);
    }

    public function testRendersJsonWhenJsonFlagSet(): void
    {
        $this->makeRequest(['json' => '1']);
        $body = \announce_controller(self::$connection, $this->settingsForTest(), self::$time, [self::HASH]);

        $decoded = json_decode($body, true);
        $this->assertIsArray($decoded);
    }

    ////	Event dispatch

    public function testStoppedEventReturnsEmptyAndDeletesPeer(): void
    {
        // Pre-existing peer that should be removed.
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
            '(`info_hash`, `peer_id`, `compactv4`, `compactv6`, `portv4`, `portv6`, `state`, `updated`) VALUES '.
            '(\''.self::HASH.'\', \''.self::PEER_ID_A.'\', \'\', \'\', 6881, 0, 1, '.self::$time.');',
        );

        $this->makeRequest(['event' => 'stopped']);
        $body = \announce_controller(self::$connection, $this->settingsForTest(), self::$time, [self::HASH]);

        $this->assertSame('', $body);
        $this->assertNull($this->fetchPeer(self::PEER_ID_A));
    }

    public function testCompletedEventIncrementsDownloads(): void
    {
        $before = $this->fetchTorrentDownloads();

        $this->makeRequest(['event' => 'completed']);
        \announce_controller(self::$connection, $this->settingsForTest(), self::$time, [self::HASH]);

        $this->assertSame($before + 1, $this->fetchTorrentDownloads());
        // Completed forces seeding state.
        $row = $this->fetchPeer(self::PEER_ID_A);
        $this->assertNotNull($row);
        $this->assertSame('1', $row['state']);
    }

    public function testDuplicateCompletedIncrementsDownloadsOnce(): void
    {
        // Clients such as Transmission send every announce twice. The second
        // 'completed' finds the peer already seeding and must not double-count.
        $before = $this->fetchTorrentDownloads();

        $this->makeRequest(['event' => 'completed']);
        \announce_controller(self::$connection, $this->settingsForTest(), self::$time, [self::HASH]);
        \announce_controller(self::$connection, $this->settingsForTest(), self::$time, [self::HASH]);

        $this->assertSame($before + 1, $this->fetchTorrentDownloads());
    }

    public function testCompletedFromAlreadySeedingPeerDoesNotIncrement(): void
    {
        // A peer already recorded as seeding (state 1) re-sending 'completed'
        // is a no-op for the counter — only the leech -> seed transition counts.
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
            '(`info_hash`, `peer_id`, `compactv4`, `compactv6`, `ipv4`, `portv4`, `portv6`, `left`, `state`, `updated`) VALUES '.
            '(\''.self::HASH.'\', \''.self::PEER_ID_A.'\', \'\', \'\', \'192.0.2.1\', 6881, 0, 0, 1, '.self::$time.');',
        );
        $before = $this->fetchTorrentDownloads();

        $this->makeRequest(['event' => 'completed']);
        \announce_controller(self::$connection, $this->settingsForTest(), self::$time, [self::HASH]);

        $this->assertSame($before, $this->fetchTorrentDownloads());
    }

    public function testDuplicateStoppedLogsEventOnce(): void
    {
        // Seed an active peer so the first 'stopped' has a row to remove (and
        // logs); the duplicate finds it already gone ($peer['old'] === false)
        // and must not re-fire the hook. Stats on, 'stopped' opted in.
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
            '(`info_hash`, `peer_id`, `compactv4`, `compactv6`, `ipv4`, `portv4`, `portv6`, `left`, `state`, `updated`) VALUES '.
            '(\''.self::HASH.'\', \''.self::PEER_ID_A.'\', \'\', \'\', \'192.0.2.1\', 6881, 0, 0, 1, '.self::$time.');',
        );

        $settings = $this->settingsForTest();
        $settings['stats_enabled'] = true;
        $settings['stats_events'] = ['stopped'];
        $settings['stats_geo'] = false;

        $this->makeRequest(['event' => 'stopped']);
        \announce_controller(self::$connection, $settings, self::$time, [self::HASH]);
        \announce_controller(self::$connection, $settings, self::$time, [self::HASH]);

        $this->assertNull($this->fetchPeer(self::PEER_ID_A));
        $this->assertSame(1, $this->fetchEventCount('stopped'));
    }

    public function testNewPeerInsertsRow(): void
    {
        $this->assertNull($this->fetchPeer(self::PEER_ID_A));

        $this->makeRequest();
        \announce_controller(self::$connection, $this->settingsForTest(), self::$time, [self::HASH]);

        $row = $this->fetchPeer(self::PEER_ID_A);
        $this->assertNotNull($row);
        $this->assertSame('192.0.2.1', $row['ipv4']);
        $this->assertSame('6881', $row['portv4']);
    }

    public function testChangedPeerReplacesRow(): void
    {
        // Pre-existing peer with a different IP — peer_changed should fire
        // and peer_insert should REPLACE the row with our request's IP.
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
            '(`info_hash`, `peer_id`, `compactv4`, `compactv6`, `ipv4`, `portv4`, `portv6`, `state`, `updated`) VALUES '.
            '(\''.self::HASH.'\', \''.self::PEER_ID_A.'\', \'\', \'\', \'10.0.0.1\', 5555, 0, 1, '.self::$time.');',
        );

        $this->makeRequest();
        \announce_controller(self::$connection, $this->settingsForTest(), self::$time, [self::HASH]);

        $row = $this->fetchPeer(self::PEER_ID_A);
        $this->assertSame('192.0.2.1', $row['ipv4']);
        $this->assertSame('6881', $row['portv4']);
    }

    public function testAccessEventUpdatesTimestamp(): void
    {
        // Pre-existing peer with the SAME IP/port — peer_changed returns
        // false and the access path bumps `updated` via peer_update.
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
            '(`info_hash`, `peer_id`, `compactv4`, `compactv6`, `ipv4`, `portv4`, `portv6`, `left`, `state`, `updated`) VALUES '.
            '(\''.self::HASH.'\', \''.self::PEER_ID_A.'\', \'\', \'\', \'192.0.2.1\', 6881, 0, 0, 1, '.(self::$time - 1000).');',
        );

        $this->makeRequest();
        \announce_controller(self::$connection, $this->settingsForTest(), self::$time, [self::HASH]);

        $row = $this->fetchPeer(self::PEER_ID_A);
        $this->assertNotNull($row);
        $this->assertSame((string)self::$time, $row['updated']);
    }

    ////	Closed tracker accept (in-process)

    public function testClosedTrackerAcceptsListedHash(): void
    {
        // open_tracker=false + hash IS in $allowed_torrents → proceeds.
        $settings = $this->settingsForTest();
        $settings['open_tracker'] = false;

        $this->makeRequest();
        $body = \announce_controller(self::$connection, $settings, self::$time, [self::HASH]);

        $this->assertStringStartsWith('d', $body);
    }

    ////	Probabilistic cleanup

    public function testCleanWithRequests100AlwaysCleans(): void
    {
        // clean_with_cron=false + clean_request_percent=100 → mt_rand(1,100)
        // is always <=100 → task_clean fires. Hard to verify directly, so
        // we just confirm the announce still completes successfully.
        $settings = self::$settings;
        $settings['clean_with_cron'] = false;
        $settings['clean_request_percent'] = 100;

        $this->makeRequest();
        $body = \announce_controller(self::$connection, $settings, self::$time, [self::HASH]);

        $this->assertStringStartsWith('d', $body);
    }

    public function testCleanWithRequests0NeverCleans(): void
    {
        // clean_with_cron=false + clean_request_percent=0 → mt_rand(1,100)
        // is always >0 → task_clean is skipped.
        $settings = self::$settings;
        $settings['clean_with_cron'] = false;
        $settings['clean_request_percent'] = 0;

        $this->makeRequest();
        $body = \announce_controller(self::$connection, $settings, self::$time, [self::HASH]);

        $this->assertStringStartsWith('d', $body);
    }

    public function testOutOfRangeIpv6PortDropsFamilyButKeepsIpv4(): void
    {
        // With allow_client_ip on, a client can supply a per-family port via
        // ?ipv6=[host]:port. An out-of-range IPv6 port must drop only the IPv6
        // family, not reject the whole announce — the valid IPv4 pair (from
        // ?port + REMOTE_ADDR) still registers.
        $settings = $this->settingsForTest();
        $settings['allow_client_ip'] = true;

        $this->makeRequest(['ipv6' => '[2606:4700:4700::1111]:99999']);
        $body = \announce_controller(self::$connection, $settings, self::$time, [self::HASH]);

        // A bencode dict, not an error exit.
        $this->assertStringStartsWith('d', $body);

        $peer = $this->fetchPeer(self::PEER_ID_A);
        $this->assertNotNull($peer);
        $this->assertSame('192.0.2.1', $peer['ipv4']);
        $this->assertSame('6881', $peer['portv4']);
        // The IPv6 family was dropped for its out-of-range port, so it's the
        // empty sentinel rather than the supplied address.
        $this->assertSame('', $peer['ipv6']);
    }

    ////	Validation failures (subprocess: tracker_error exits)

    /**
     * Spawn a fresh PHP process that bootstraps phoenix.php, requires the
     * controller, sets up the request, optionally overrides settings, and
     * calls the controller. Captures the bencode error body + exit(2)
     * from tracker_error.
     *
     * @param array<string, string|int> $get
     * @param array<string, mixed>      $settingsOverrides
     * @param array<int, string>        $allowedTorrents
     */
    private function runControllerSubprocess(
        array $get,
        array $settingsOverrides = [],
        array $allowedTorrents = [],
    ): array {
        $bootstrap = __DIR__.'/../bootstrap.php';
        $script = '<?php '.
            'require '.var_export($bootstrap, true).'; '.
            '$_GET    = '.var_export($get, true).'; '.
            '$_SERVER["QUERY_STRING"] = '.var_export(http_build_query($get), true).'; '.
            '$_SERVER["REMOTE_ADDR"]  = "192.0.2.1"; '.
            '$settings = array_merge($GLOBALS["phoenix_settings"], '.var_export($settingsOverrides, true).'); '.
            'require '.var_export(self::CONTROLLER_PATH, true).'; '.
            'echo announce_controller($GLOBALS["phoenix_connection"], $settings, $GLOBALS["phoenix_time"], '.var_export($allowedTorrents, true).');';

        return $this->runPhpSubprocess($script);
    }

    public function testRejectsInvalidInfoHash(): void
    {
        $result = $this->runControllerSubprocess([
            'info_hash' => 'not_a_valid_hash',
            'peer_id' => self::PEER_ID_A,
            'port' => '6881',
        ]);
        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Info Hash is invalid', $result['stdout']);
    }

    public function testRejectsInvalidPeerId(): void
    {
        $result = $this->runControllerSubprocess(
            [
                'info_hash' => self::HASH,
                'peer_id' => 'not_a_valid_peer_id',
                'port' => '6881',
            ],
            ['open_tracker' => true],
            [self::HASH],
        );
        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Peer ID is invalid', $result['stdout']);
    }

    public function testRejectsPortAboveMax(): void
    {
        // A port outside the smallint(5) unsigned range (1-65535) would overflow
        // the peers column; the controller rejects it up front rather than
        // failing at insert time and silently dropping the peer.
        $result = $this->runControllerSubprocess(
            [
                'info_hash' => self::HASH,
                'peer_id' => self::PEER_ID_A,
                'port' => '70000',
            ],
            ['open_tracker' => true],
            [self::HASH],
        );
        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Missing or invalid port', $result['stdout']);
    }

    public function testRejectsNegativePort(): void
    {
        $result = $this->runControllerSubprocess(
            [
                'info_hash' => self::HASH,
                'peer_id' => self::PEER_ID_A,
                'port' => '-1',
            ],
            ['open_tracker' => true],
            [self::HASH],
        );
        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Missing or invalid port', $result['stdout']);
    }

    public function testRejectsMissingPort(): void
    {
        // A resolvable IP but no port — ?port omitted and REMOTE_ADDR carries
        // none — can't be connected to. The controller rejects it as a client
        // fault ("Missing or invalid port.") rather than letting the insert bind
        // a false port into the NOT NULL column.
        $result = $this->runControllerSubprocess(
            [
                'info_hash' => self::HASH,
                'peer_id' => self::PEER_ID_A,
                // no 'port'
            ],
            ['open_tracker' => true],
            [self::HASH],
        );
        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Missing port', $result['stdout']);
    }

    public function testRejectsZeroPort(): void
    {
        // ?port=0 is falsy, so no port is set and no ip:port fallback fires —
        // the same "no usable port" case as omitting it entirely.
        $result = $this->runControllerSubprocess(
            [
                'info_hash' => self::HASH,
                'peer_id' => self::PEER_ID_A,
                'port' => '0',
            ],
            ['open_tracker' => true],
            [self::HASH],
        );
        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Missing port', $result['stdout']);
    }

    public function testClosedTrackerRejectsUnlistedHash(): void
    {
        // open_tracker=false + $allowed_torrents empty → "Torrent is not
        // allowed." instead of falling through to peer_id validation.
        $result = $this->runControllerSubprocess(
            [
                'info_hash' => self::HASH,
                'peer_id' => self::PEER_ID_A,
                'port' => '6881',
            ],
            ['open_tracker' => false],
            [],
        );
        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Torrent is not allowed', $result['stdout']);
    }

}
