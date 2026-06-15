<?php

declare(strict_types=1);

namespace Phoenix\Tests\Smoke;

use PHPUnit\Framework\Attributes\Depends;

require_once __DIR__.'/SmokeTestCase.php';

// End-to-end smoke tests: drive every public/*.php entry point over real HTTP,
// walking the deployment lifecycle install -> use. Methods run in declaration
// order; the post-install tests #[Depends] on testInstallSucceeds so they skip
// cleanly (rather than error) if the install step fails. Requires a running
// `php -S` server with NO config/phoenix.custom.php at start (installer mode)
// and a reachable, empty database (see the smoke-php.yml workflow).
class EndpointSmokeTest extends SmokeTestCase
{
    private const HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const PEER_ID = '1111111111111111111111111111111111111111';
    private const ADMIN_PW = 'smoke-admin-pw';

    //// Pre-config (no config file yet -> installer mode)

    public function testMagnetGeneratorRenders(): void
    {
        // magnet.php is self-contained (no bootstrap), so it works with no config.
        $r = $this->get('/magnet.php');
        $this->assertSame(200, $r['status']);
        $this->assertStringContainsString('Magnet Generator', $r['body']);
        $this->assertStringContainsString('/announce.php', $r['body']);
    }

    public function testInstallerShowsSetupForm(): void
    {
        $r = $this->get('/admin.php');
        $this->assertSame(200, $r['status']);
        $this->assertStringContainsString('Phoenix Setup', $r['body']);
        $this->assertStringContainsString('name="process" value="install"', $r['body']);
    }

    public function testInstallerRejectsEmptyPassword(): void
    {
        // The P1.1 guard fires before any DB work, so this holds without a DB.
        $r = $this->post('/admin.php', ['process' => 'install'] + $this->dbCreds());
        $this->assertSame(200, $r['status']);
        $this->assertStringContainsString('Set an admin password', $r['body']);
    }

    public function testInstallSucceeds(): void
    {
        $r = $this->post('/admin.php', [
            'process' => 'install',
            'admin_password' => self::ADMIN_PW,
            'open_tracker' => '1',
            'public_index' => '1',
        ] + $this->dbCreds());

        $this->assertSame(302, $r['status']);
        $this->assertStringContainsString('installed=1', (string) $this->headerValue($r, 'Location'));
    }

    //// Post-config (config + tables now exist)

    #[Depends('testInstallSucceeds')]
    public function testAdminLoginFormShown(): void
    {
        $r = $this->get('/admin.php');
        $this->assertSame(200, $r['status']);
        $this->assertStringContainsString('name="password"', $r['body']);
        $this->assertStringNotContainsString('Phoenix Setup', $r['body']);
    }

    #[Depends('testInstallSucceeds')]
    public function testAdminLoginRedirectsOnCorrectPassword(): void
    {
        $r = $this->post('/admin.php', ['process' => 'login', 'password' => self::ADMIN_PW]);
        $this->assertSame(302, $r['status']);
    }

    #[Depends('testInstallSucceeds')]
    public function testAnnounceReturnsBencode(): void
    {
        $r = $this->get('/announce.php', [
            'info_hash' => self::HASH,
            'peer_id' => self::PEER_ID,
            'port' => '6881',
            'left' => '0',
        ]);
        $this->assertSame(200, $r['status']);
        $this->assertStringStartsWith('d', $r['body']);
        $this->assertStringContainsString('8:intervali1800e', $r['body']);
        $this->assertStringContainsString('5:peers', $r['body']);
    }

    #[Depends('testInstallSucceeds')]
    public function testScrapeStatsReturnsJson(): void
    {
        $r = $this->get('/scrape.php', ['stats' => '1', 'json' => '1']);
        $this->assertSame(200, $r['status']);
        $this->assertIsArray(json_decode($r['body'], true));
    }

    #[Depends('testInstallSucceeds')]
    public function testScrapeSpecificReflectsAnnouncedPeer(): void
    {
        $r = $this->get('/scrape.php', ['info_hash' => self::HASH, 'json' => '1']);
        $this->assertSame(200, $r['status']);
        $decoded = json_decode($r['body'], true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey(self::HASH, $decoded);
    }

    #[Depends('testInstallSucceeds')]
    public function testFullScrapeReturnsBencode(): void
    {
        $r = $this->get('/scrape.php');
        $this->assertSame(200, $r['status']);
        $this->assertStringStartsWith('d', $r['body']);
        $this->assertStringContainsString('5:files', $r['body']);
    }

    #[Depends('testInstallSucceeds')]
    public function testPublicIndexLists(): void
    {
        // public_index was enabled during install.
        $r = $this->get('/index.php');
        $this->assertSame(200, $r['status']);
        $this->assertStringContainsString('<', $r['body']);
    }

    #[Depends('testInstallSucceeds')]
    public function testPublicIndexAsXml(): void
    {
        $r = $this->get('/index.php', ['xml' => '1']);
        $this->assertSame(200, $r['status']);
        $this->assertStringContainsString('<torrents>', $r['body']);
    }

    #[Depends('testInstallSucceeds')]
    public function testPublicIndexAsJson(): void
    {
        $r = $this->get('/index.php', ['json' => '1']);
        $this->assertSame(200, $r['status']);
        $this->assertIsArray(json_decode($r['body'], true));
    }

    #[Depends('testInstallSucceeds')]
    public function testAdminPanelReachableAfterLogin(): void
    {
        // Log in, capture the regenerated session cookie, then load the panel
        // with it — exercises the authenticated admin path end-to-end.
        $login = $this->post('/admin.php', ['process' => 'login', 'password' => self::ADMIN_PW]);
        $this->assertSame(302, $login['status']);

        $cookie = $this->sessionCookie($login);
        $this->assertNotNull($cookie, 'a successful login should set a session cookie');

        $panel = $this->get('/admin.php', [], ['Cookie' => $cookie]);
        $this->assertSame(200, $panel['status']);
        // The authenticated dashboard renders the admin nav and its Dashboard
        // title — neither appears on the login form.
        $this->assertStringContainsString('<nav class="admin-nav">', $panel['body']);
        $this->assertStringContainsString('<title>Phoenix Admin: Dashboard</title>', $panel['body']);
        $this->assertStringNotContainsString('name="password"', $panel['body']);

        // The server diagnostics moved to their own Server Support page.
        $support = $this->get('/admin.php', ['page' => 'support'], ['Cookie' => $cookie]);
        $this->assertSame(200, $support['status']);
        $this->assertStringContainsString('<title>Phoenix Admin: Server Support</title>', $support['body']);
        $this->assertStringContainsString('PHP Version:', $support['body']);
    }

    #[Depends('testInstallSucceeds')]
    public function testAdminLogoutRedirectsAndEndsSession(): void
    {
        $login = $this->post('/admin.php', ['process' => 'login', 'password' => self::ADMIN_PW]);
        $this->assertSame(302, $login['status']);
        $cookie = $this->sessionCookie($login);
        $this->assertNotNull($cookie);

        // Logout is POST-only AND CSRF-protected (see #59): a POST without a
        // valid token is refused — no redirect, session left intact — which
        // also exercises the reject branch of auth_handle_logout server-side.
        $forged = $this->post('/admin.php', ['logout' => '1'], ['Cookie' => $cookie]);
        $this->assertSame(200, $forged['status']);

        // Fetch the panel to read the per-session token from the logout form.
        $panel = $this->get('/admin.php', [], ['Cookie' => $cookie]);
        $this->assertSame(200, $panel['status']);
        $token = $this->csrfToken($panel['body']);
        $this->assertNotNull($token, 'panel should embed a CSRF token in the logout form');

        // With the token, logout destroys the session and redirects (header +
        // exit — uncoverable in-process, exercised here in the PCOV'd server).
        $logout = $this->post('/admin.php', ['logout' => '1', 'csrf' => $token], ['Cookie' => $cookie]);
        $this->assertSame(302, $logout['status']);

        // The session is gone: the same cookie now falls back to the login form
        // (no authenticated admin nav).
        $after = $this->get('/admin.php', [], ['Cookie' => $cookie]);
        $this->assertSame(200, $after['status']);
        $this->assertStringContainsString('name="password"', $after['body']);
        $this->assertStringNotContainsString('<nav class="admin-nav">', $after['body']);
    }

    #[Depends('testInstallSucceeds')]
    public function testAdminPanelAddsTorrentFromUpload(): void
    {
        // Drive the admin add-torrent form end-to-end: log in for a session +
        // CSRF token, build a single-file .torrent, and POST it multipart to
        // the Add Torrent page (admin.php?page=add) with process=torrent_add.
        // The page re-renders with the action's message. Uses a DISTINCT
        // info_hash from the API smoke tests.
        require_once dirname(__DIR__, 2).'/src/functions/bencode.encode.php';

        $session = $this->adminSession();

        $info = [
            'name' => 'Admin Panel Upload.iso',
            'length' => 5150,
            'piece length' => 16384,
            'pieces' => str_repeat("\x00", 20),
        ];
        $raw = \bencode_encode(['info' => $info]);
        $hash = sha1(\bencode_encode($info));
        $this->assertNotSame(str_repeat('d', 40), $hash);

        $r = $this->postMultipart(
            '/admin.php?page=add',
            [
                'process' => 'torrent_add',
                'csrf' => $session['csrf'],
            ],
            [
                'name' => 'torrent',
                'filename' => 'admin-upload.torrent',
                'content' => $raw,
                'type' => 'application/x-bittorrent',
            ],
            ['Cookie' => $session['cookie']],
        );
        $this->assertSame(200, $r['status'], $r['body']);
        $this->assertStringContainsString('Torrent added.', $r['body']);

        // The admin-added torrent records a NULL owner.
        $db = $this->db();
        $prefix = $this->dbCreds()['db_prefix'];
        $this->assertSame(
            1,
            $this->scalar($db, "SELECT COUNT(*) FROM `{$prefix}torrents` WHERE `info_hash`='{$hash}' AND `user` IS NULL"),
        );
    }

    //// bin/ cron entry points (CLI, not HTTP)

    #[Depends('testInstallSucceeds')]
    public function testCleanAndOptimizeCronRemovesStalePeers(): void
    {
        $db = $this->db();
        $prefix = $this->dbCreds()['db_prefix'];

        // The cron maintenance is gated on clean_with_cron, which the installer
        // leaves off — enable it so the script's body actually runs. Set an
        // events retention so the ledger-pruning side runs too.
        $this->enableCleanWithCron();
        $this->appendConfigOverride("\$settings['stats_retention'] = 30;");

        // Seed a stale peer (older than 3x announce_interval = 5400s) and a
        // fresh one, under a distinct info_hash with plain-hex peer_ids (so the
        // __TEST_ sentinel purge doesn't catch the one we expect to survive).
        $hash = str_repeat('c', 40);
        $stalePeer = str_repeat('9', 40);
        $freshPeer = str_repeat('8', 40);
        $now = time();
        $stale = $now - (3 * 1800) - 600;
        foreach ([[$stalePeer, $stale], [$freshPeer, $now]] as [$pid, $updated]) {
            mysqli_query(
                $db,
                "REPLACE INTO `{$prefix}peers` ".
                '(`info_hash`,`peer_id`,`compactv4`,`compactv6`,`portv4`,`portv6`,`state`,`updated`) '.
                "VALUES ('{$hash}','{$pid}','','',0,0,0,{$updated})",
            );
        }

        // Seed an expired event (31 days old with 30-day retention) and a
        // fresh one under the same hash; cleanup must prune only the expired.
        $oldEvent = $now - (31 * 86400);
        foreach ([$oldEvent, $now] as $eventTime) {
            mysqli_query(
                $db,
                "INSERT INTO `{$prefix}events` (`time`,`info_hash`,`event`) ".
                "VALUES ({$eventTime},'{$hash}','completed')",
            );
        }

        $r = $this->runCli('clean-and-optimize.php');
        $this->assertSame(0, $r['exit'], $r['stdout'].$r['stderr']);

        // Cleanup is selective: the stale peer is gone, the fresh one survives.
        $this->assertSame(0, $this->scalar($db, "SELECT COUNT(*) FROM `{$prefix}peers` WHERE `peer_id`='{$stalePeer}'"));
        $this->assertSame(1, $this->scalar($db, "SELECT COUNT(*) FROM `{$prefix}peers` WHERE `peer_id`='{$freshPeer}'"));
        // Same selectivity for the events ledger under stats_retention.
        $this->assertSame(0, $this->scalar($db, "SELECT COUNT(*) FROM `{$prefix}events` WHERE `info_hash`='{$hash}' AND `time` <= {$oldEvent}"));
        $this->assertSame(1, $this->scalar($db, "SELECT COUNT(*) FROM `{$prefix}events` WHERE `info_hash`='{$hash}'"));
        // ...and the run logged a `clean` task.
        $this->assertGreaterThan(0, $this->scalar($db, "SELECT COUNT(*) FROM `{$prefix}tasks` WHERE `name`='clean'"));
    }

    #[Depends('testInstallSucceeds')]
    public function testBackupDatabaseCronRuns(): void
    {
        $root = dirname(__DIR__, 2);
        @mkdir($root.'/backups');

        // Seed a backup old enough to be rotated out (backup_rotate defaults to
        // 30 days) so the rotation pass actually deletes something.
        $oldBackup = $root.'/backups/'.$this->dbCreds()['db_name'].'.20000101_0000.sql';
        file_put_contents($oldBackup, "-- stale backup\n");
        touch($oldBackup, time() - (40 * 86400));

        // Seed a torrent row so the dump has real torrent data to check: a
        // completed announce creates one via torrent_increment_downloads (and a
        // peer row), exercising both sides of the data-vs-schema split.
        $this->get('/announce.php', [
            'info_hash' => self::HASH,
            'peer_id' => self::PEER_ID,
            'port' => '6881',
            'left' => '0',
            'event' => 'completed',
        ]);

        $r = $this->runCli('backup-database.php');
        $this->assertSame(0, $r['exit'], $r['stdout'].$r['stderr']);

        $dumps = glob($root.'/backups/'.$this->dbCreds()['db_name'].'.*.sql');
        $this->assertNotEmpty($dumps, 'backup-database should write a .sql dump');

        $dump = (string) file_get_contents($dumps[0]);
        $prefix = $this->dbCreds()['db_prefix'];
        // torrents + tasks + the peers structure are all present...
        $this->assertStringContainsString($prefix.'torrents', $dump);
        $this->assertStringContainsString($prefix.'tasks', $dump);
        $this->assertStringContainsString($prefix.'peers', $dump);
        // ...the torrent row IS dumped (its info_hash appears as data), but the
        // peer row is NOT — peers is schema-only.
        $this->assertStringContainsString(self::HASH, $dump);
        $this->assertStringNotContainsString(self::PEER_ID, $dump);

        // Rotation deleted the stale backup (older than backup_rotate days).
        $this->assertFileDoesNotExist($oldBackup);
    }

    #[Depends('testInstallSucceeds')]
    public function testBackupDatabaseFailsWhenDirMissing(): void
    {
        // Point backup_dir at a path that doesn't exist: the script must bail
        // out cleanly (exit 1) rather than try to dump.
        $this->appendConfigOverride(
            "\$settings['backup_dir'] = '".sys_get_temp_dir().'/phoenix-no-such-'.bin2hex(random_bytes(4))."';",
        );

        $r = $this->runCli('backup-database.php');
        $this->assertSame(1, $r['exit']);
        $this->assertStringContainsString('BACKUP_DIR_NOT_FOUND', $r['stdout']);
    }

    #[Depends('testInstallSucceeds')]
    public function testApiAddsTorrentToTheIndex(): void
    {
        // Enable the API (the installer writes no keys), add a listed torrent,
        // and confirm it shows up on the public index — exercises
        // public/api/torrent/add.php end-to-end in both serialisations. Auth is
        // the Authorization: Bearer header; the endpoint is POST only.
        $this->appendConfigOverride("\$settings['api_keys'] = ['smoke' => 'smoke-api-key'];");

        $hash = str_repeat('d', 40);
        $r = $this->post('/api/torrent/add.php', [
            'info_hash' => $hash,
            'name' => 'Smoke API Torrent',
            'size' => '2048',
        ], $this->bearer('smoke-api-key'));
        $this->assertSame(200, $r['status']);
        $decoded = json_decode($r['body'], true);
        $this->assertIsArray($decoded);
        $this->assertSame('smoke', $decoded['torrent']['user']);
        $this->assertSame('Smoke API Torrent', $decoded['torrent']['name']);

        // The endpoint is add-only: re-POSTing the same hash is refused,
        // and with ?xml the error serialises as XML.
        $xml = $this->post('/api/torrent/add.php?xml=1', ['info_hash' => $hash], $this->bearer('smoke-api-key'));
        $this->assertSame(200, $xml['status']);
        $this->assertStringContainsString('<error>Torrent already exists.</error>', $xml['body']);

        // A wrong key is refused, and the error serialises as JSON by default.
        $bad = $this->post('/api/torrent/add.php', ['info_hash' => $hash], $this->bearer('wrong-key'));
        $this->assertSame(200, $bad['status']);
        $this->assertSame(['error' => 'API key is invalid.'], json_decode($bad['body'], true));

        // The torrent was added listed, so the public index now carries it.
        $idx = $this->get('/index.php', ['json' => '1']);
        $this->assertSame(200, $idx['status']);
        $this->assertStringContainsString($hash, $idx['body']);
        $this->assertStringContainsString('Smoke API Torrent', $idx['body']);
    }

    #[Depends('testInstallSucceeds')]
    public function testApiAddsTorrentFromUpload(): void
    {
        // Drive the API's server-side .torrent parse path: build a single-file
        // torrent in-test, upload it multipart, and assert the response carries
        // the parsed name/size/meta. The API key was enabled by the earlier
        // testApiAddsTorrentToTheIndex; this run uses a DISTINCT info_hash
        // (derived from the fixture, never str_repeat('d',40)).
        require_once dirname(__DIR__, 2).'/src/functions/bencode.encode.php';

        $info = [
            'name' => 'Uploaded Smoke.iso',
            'length' => 4242,
            'piece length' => 16384,
            'pieces' => str_repeat("\x00", 20),
        ];
        $raw = \bencode_encode([
            'info' => $info,
            'announce' => 'http://tracker.smoke/announce',
            'url-list' => 'http://seed.smoke/files/',
        ]);
        $hash = sha1(\bencode_encode($info));
        $this->assertNotSame(str_repeat('d', 40), $hash);

        $r = $this->postMultipart(
            '/api/torrent/add.php',
            [],
            [
                'name' => 'torrent',
                'filename' => 'upload.torrent',
                'content' => $raw,
                'type' => 'application/x-bittorrent',
            ],
            $this->bearer('smoke-api-key'),
        );
        $this->assertSame(200, $r['status'], $r['body']);

        $decoded = json_decode($r['body'], true);
        $this->assertIsArray($decoded);
        $this->assertSame($hash, $decoded['torrent']['info_hash']);
        $this->assertSame('Uploaded Smoke.iso', $decoded['torrent']['name']);
        $this->assertSame(4242, $decoded['torrent']['size']);
        $this->assertSame('Uploaded Smoke.iso', $decoded['torrent']['filename']);
        $this->assertSame([['path' => 'Uploaded Smoke.iso', 'length' => 4242]], $decoded['torrent']['files']);
        $this->assertSame(['http://tracker.smoke/announce'], $decoded['torrent']['trackers']);
        $this->assertSame(['http://seed.smoke/files/'], $decoded['torrent']['webseeds']);
    }

    #[Depends('testInstallSucceeds')]
    public function testApiListsAllTorrents(): void
    {
        // The two earlier API tests added torrents under the 'smoke' key; GET
        // /api/torrents scopes a normal key to its OWN torrents, so the 'smoke'
        // key surfaces them with their user and listed flag. Header-authed.
        $listHash = str_repeat('d', 40);

        $r = $this->get('/api/torrents.php', [], $this->bearer('smoke-api-key'));
        $this->assertSame(200, $r['status'], $r['body']);
        $decoded = json_decode($r['body'], true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('torrents', $decoded);

        $row = null;
        foreach ($decoded['torrents'] as $torrent) {
            if (($torrent['info_hash'] ?? null) === $listHash) {
                $row = $torrent;
                break;
            }
        }
        $this->assertNotNull($row, 'the added torrent should appear in /api/torrents');
        $this->assertSame('smoke', $row['user']);
        $this->assertSame(1, $row['listed']);
        $this->assertSame('Smoke API Torrent', $row['name']);

        // ?xml serialises the same collection as XML.
        $xml = $this->get('/api/torrents.php', ['xml' => '1'], $this->bearer('smoke-api-key'));
        $this->assertSame(200, $xml['status']);
        $this->assertStringContainsString('<torrents>', $xml['body']);
        $this->assertStringContainsString('<info_hash>'.$listHash.'</info_hash>', $xml['body']);
        $this->assertStringContainsString('<user>smoke</user>', $xml['body']);

        // A wrong key is refused (auth shared with the add endpoint).
        $bad = $this->get('/api/torrents.php', [], $this->bearer('wrong-key'));
        $this->assertSame(200, $bad['status']);
        $this->assertSame(['error' => 'API key is invalid.'], json_decode($bad['body'], true));

        // No credential at all → Authorization required.
        $none = $this->get('/api/torrents.php');
        $this->assertSame(200, $none['status']);
        $this->assertSame(['error' => 'Authorization required.'], json_decode($none['body'], true));
    }

    #[Depends('testInstallSucceeds')]
    public function testApiIndexReturnsVersion(): void
    {
        // The /api discovery index returns the running Phoenix version,
        // unauthenticated (no key), in both serialisations.
        $r = $this->get('/api/index.php');
        $this->assertSame(200, $r['status'], $r['body']);
        $decoded = json_decode($r['body'], true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('phoenix', $decoded);
        $this->assertStringContainsString('v4.', (string) $decoded['phoenix']['version']);

        $xml = $this->get('/api/index.php', ['xml' => '1']);
        $this->assertSame(200, $xml['status']);
        $this->assertStringContainsString('<version>', $xml['body']);
        $this->assertStringContainsString('Phoenix', $xml['body']);
    }

    #[Depends('testInstallSucceeds')]
    public function testApiDelistAndListTogglesPublicIndex(): void
    {
        // Expand the API keys: an 'other' owner (for the ownership-refusal test)
        // and the '*' admin. The latest api_keys assignment wins, so 'smoke'
        // keeps working alongside the new owners.
        $this->appendConfigOverride(
            "\$settings['api_keys'] = ['smoke' => 'smoke-api-key', 'other' => 'other-api-key', '*' => 'admin-api-key'];",
        );

        $hash = str_repeat('d', 40);

        // Delist it: the public index must drop it.
        $delist = $this->post('/api/torrent/delist.php', ['info_hash' => $hash], $this->bearer('smoke-api-key'));
        $this->assertSame(200, $delist['status'], $delist['body']);
        $this->assertSame(0, json_decode($delist['body'], true)['torrent']['listed']);
        $this->assertStringNotContainsString($hash, $this->get('/index.php', ['json' => '1'])['body']);

        // List it again: the index carries it once more.
        $list = $this->post('/api/torrent/list.php', ['info_hash' => $hash], $this->bearer('smoke-api-key'));
        $this->assertSame(200, $list['status'], $list['body']);
        $this->assertSame(1, json_decode($list['body'], true)['torrent']['listed']);
        $this->assertStringContainsString($hash, $this->get('/index.php', ['json' => '1'])['body']);
    }

    #[Depends('testApiDelistAndListTogglesPublicIndex')]
    public function testApiMutationRejectsGet(): void
    {
        // The mutation endpoints are POST only; a GET is refused before auth.
        $r = $this->get('/api/torrent/delist.php', ['info_hash' => str_repeat('d', 40)], $this->bearer('smoke-api-key'));
        $this->assertSame(200, $r['status']);
        $this->assertSame(['error' => 'Method not allowed.'], json_decode($r['body'], true));
    }

    #[Depends('testApiDelistAndListTogglesPublicIndex')]
    public function testApiMutationRejectsAnotherUsersKey(): void
    {
        // 'd' is owned by 'smoke'; the 'other' key gets the non-disclosing
        // 'Torrent not found.' and the torrent is left untouched (still listed).
        $hash = str_repeat('d', 40);
        $r = $this->post('/api/torrent/delist.php', ['info_hash' => $hash], $this->bearer('other-api-key'));
        $this->assertSame(200, $r['status']);
        $this->assertSame(['error' => 'Torrent not found.'], json_decode($r['body'], true));
        $this->assertStringContainsString($hash, $this->get('/index.php', ['json' => '1'])['body']);
    }

    #[Depends('testApiDelistAndListTogglesPublicIndex')]
    public function testApiTorrentsScopedByOwnerUnlessAdmin(): void
    {
        // 'd' is owned by 'smoke'. A normal owner sees only its own torrents;
        // the '*' admin sees every torrent.
        $hash = str_repeat('d', 40);

        $smoke = $this->get('/api/torrents.php', [], $this->bearer('smoke-api-key'));
        $this->assertStringContainsString($hash, $smoke['body']);

        // 'other' owns nothing here, so 'd' must not appear in its scoped list.
        $other = $this->get('/api/torrents.php', [], $this->bearer('other-api-key'));
        $this->assertStringNotContainsString($hash, $other['body']);

        // The admin sees it (and everything else).
        $admin = $this->get('/api/torrents.php', [], $this->bearer('admin-api-key'));
        $this->assertStringContainsString($hash, $admin['body']);
    }

    #[Depends('testApiDelistAndListTogglesPublicIndex')]
    public function testApiAdminKeyMayMutateAnotherUsersTorrent(): void
    {
        // The '*' admin key acts on a torrent it does not own, then restores it.
        $hash = str_repeat('d', 40);
        $delist = $this->post('/api/torrent/delist.php', ['info_hash' => $hash], $this->bearer('admin-api-key'));
        $this->assertSame(200, $delist['status'], $delist['body']);
        $this->assertSame(0, json_decode($delist['body'], true)['torrent']['listed']);

        $this->post('/api/torrent/list.php', ['info_hash' => $hash], $this->bearer('admin-api-key'));
        $this->assertStringContainsString($hash, $this->get('/index.php', ['json' => '1'])['body']);
    }

    #[Depends('testApiDelistAndListTogglesPublicIndex')]
    public function testApiAdminSessionMayMutateWithCsrf(): void
    {
        // The session auth path: a logged-in admin (no API key) may drive the
        // mutation endpoints, but only with a valid CSRF token.
        $hash = str_repeat('d', 40);
        $session = $this->adminSession();

        // A live session WITHOUT a CSRF token is refused (cookie alone can't
        // authorise a state change — that's the CSRF guard).
        $noCsrf = $this->post('/api/torrent/delist.php', ['info_hash' => $hash], ['Cookie' => $session['cookie']]);
        $this->assertSame(200, $noCsrf['status']);
        $this->assertSame(['error' => 'CSRF token is invalid.'], json_decode($noCsrf['body'], true));
        // Untouched: still listed.
        $this->assertStringContainsString($hash, $this->get('/index.php', ['json' => '1'])['body']);

        // With the token, the session authorises as the admin.
        $ok = $this->post(
            '/api/torrent/delist.php',
            ['info_hash' => $hash, 'csrf' => $session['csrf']],
            ['Cookie' => $session['cookie']],
        );
        $this->assertSame(200, $ok['status'], $ok['body']);
        $this->assertSame(0, json_decode($ok['body'], true)['torrent']['listed']);

        // Restore via the same session.
        $this->post(
            '/api/torrent/list.php',
            ['info_hash' => $hash, 'csrf' => $session['csrf']],
            ['Cookie' => $session['cookie']],
        );
        $this->assertStringContainsString($hash, $this->get('/index.php', ['json' => '1'])['body']);
    }

    #[Depends('testApiDelistAndListTogglesPublicIndex')]
    public function testApiDeleteRemovesTorrentAndPeers(): void
    {
        // Add a dedicated torrent owned by 'smoke' and seed it a couple of peers
        // directly (so the owner isn't disturbed by an announce).
        $hash = str_repeat('1', 40);
        $add = $this->post('/api/torrent/add.php', [
            'info_hash' => $hash,
            'name' => 'Doomed Torrent',
        ], $this->bearer('smoke-api-key'));
        $this->assertSame(200, $add['status'], $add['body']);

        $db = $this->db();
        $prefix = $this->dbCreds()['db_prefix'];
        foreach ([str_repeat('a', 40), str_repeat('b', 40)] as $pid) {
            mysqli_query(
                $db,
                "INSERT INTO `{$prefix}peers` ".
                '(`info_hash`,`peer_id`,`compactv4`,`compactv6`,`portv4`,`portv6`,`state`,`updated`) '.
                "VALUES ('{$hash}','{$pid}','','',0,0,0,".time().')',
            );
        }
        $this->assertSame(2, $this->scalar($db, "SELECT COUNT(*) FROM `{$prefix}peers` WHERE `info_hash`='{$hash}'"));

        // Deletion is off by default: 'smoke' (a non-admin) is refused and the
        // torrent survives — the gate fires before any lookup.
        $disabled = $this->post('/api/torrent/delete.php', ['info_hash' => $hash], $this->bearer('smoke-api-key'));
        $this->assertSame(200, $disabled['status']);
        $this->assertSame(['error' => 'Torrent deletion is disabled.'], json_decode($disabled['body'], true));
        $this->assertSame(1, $this->scalar($db, "SELECT COUNT(*) FROM `{$prefix}torrents` WHERE `info_hash`='{$hash}'"));

        // Enable deletion, then 'smoke' can remove its own torrent.
        $this->appendConfigOverride("\$settings['api_allow_delete'] = true;");
        $del = $this->post('/api/torrent/delete.php', ['info_hash' => $hash], $this->bearer('smoke-api-key'));
        $this->assertSame(200, $del['status'], $del['body']);
        $this->assertSame($hash, json_decode($del['body'], true)['torrent']['info_hash']);

        // Gone from the table, from /api/torrents, and its peers are removed.
        $this->assertSame(0, $this->scalar($db, "SELECT COUNT(*) FROM `{$prefix}torrents` WHERE `info_hash`='{$hash}'"));
        $this->assertSame(0, $this->scalar($db, "SELECT COUNT(*) FROM `{$prefix}peers` WHERE `info_hash`='{$hash}'"));
        $this->assertStringNotContainsString($hash, $this->get('/api/torrents.php', [], $this->bearer('smoke-api-key'))['body']);
    }

    /**
     * The Authorization header carrying an API key, as the management API now
     * expects it (`Authorization: Bearer <key>`).
     *
     * @return array{Authorization: string}
     */
    private function bearer(string $key): array
    {
        return ['Authorization' => 'Bearer '.$key];
    }

    /**
     * Log in as admin and return the authenticated session cookie plus the
     * CSRF token embedded in the panel — the two credentials the API's session
     * auth path needs.
     *
     * @return array{cookie: string, csrf: string}
     */
    private function adminSession(): array
    {
        $login = $this->post('/admin.php', ['process' => 'login', 'password' => self::ADMIN_PW]);
        $this->assertSame(302, $login['status']);
        $cookie = $this->sessionCookie($login);
        $this->assertNotNull($cookie);

        $panel = $this->get('/admin.php', [], ['Cookie' => $cookie]);
        $token = $this->csrfToken($panel['body']);
        $this->assertNotNull($token, 'panel should embed a CSRF token');

        return ['cookie' => $cookie, 'csrf' => $token];
    }

    #[Depends('testInstallSucceeds')]
    public function testStatsEnabledLogsCompletedEvent(): void
    {
        // Flip stats on via a config override, then complete a download for a
        // fresh distinct hash. The download.complete hook should log exactly one
        // 'completed' row to the events table (created at install, empty until
        // now). Runs before the closed-tracker test so the tracker is still open.
        $this->appendConfigOverride("\$settings['stats_enabled'] = true;");

        $db = $this->db();
        $prefix = $this->dbCreds()['db_prefix'];
        $hash = str_repeat('f', 40);

        $r = $this->get('/announce.php', [
            'info_hash' => $hash,
            'peer_id' => self::PEER_ID,
            'port' => '6881',
            'left' => '0',
            'event' => 'completed',
        ]);
        $this->assertSame(200, $r['status']);

        $this->assertSame(
            1,
            $this->scalar(
                $db,
                "SELECT COUNT(*) FROM `{$prefix}events` WHERE `info_hash`='{$hash}' AND `event`='completed'",
            ),
        );
    }

    #[Depends('testInstallSucceeds')]
    public function testClosedTrackerRejectsScrapeInEachFormat(): void
    {
        // Close the tracker — the installer opens it. This runs LAST, so nothing
        // afterwards relies on open_tracker and no restore is needed.
        $this->closeTracker();

        // A never-tracked hash can't be in the allowed list, so a closed tracker
        // refuses to scrape it via tracker_error(). Hitting all three of its
        // serialisations covers the bencode/xml/json branches + error views in
        // the PCOV'd server — the exit(2) makes them uncoverable in-process.
        $hash = str_repeat('e', 40);

        $bencode = $this->get('/scrape.php', ['info_hash' => $hash]);
        $this->assertSame(200, $bencode['status']);
        $this->assertStringContainsString('Torrent is not allowed', $bencode['body']);

        $xml = $this->get('/scrape.php', ['info_hash' => $hash, 'xml' => '1']);
        $this->assertSame(200, $xml['status']);
        $this->assertStringContainsString('<error>', $xml['body']);
        $this->assertStringContainsString('Torrent is not allowed', $xml['body']);

        $json = $this->get('/scrape.php', ['info_hash' => $hash, 'json' => '1']);
        $this->assertSame(200, $json['status']);
        $this->assertIsArray(json_decode($json['body'], true));
        $this->assertStringContainsString('Torrent is not allowed', $json['body']);
    }
}
