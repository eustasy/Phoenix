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
        $this->assertStringContainsString('Phoenix Diagnostics and Utilities', $panel['body']);
        $this->assertStringNotContainsString('name="password"', $panel['body']);
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

        // The session is gone: the same cookie now falls back to the login form.
        $after = $this->get('/admin.php', [], ['Cookie' => $cookie]);
        $this->assertSame(200, $after['status']);
        $this->assertStringContainsString('name="password"', $after['body']);
        $this->assertStringNotContainsString('Phoenix Diagnostics and Utilities', $after['body']);
    }

    //// bin/ cron entry points (CLI, not HTTP)

    #[Depends('testInstallSucceeds')]
    public function testCleanAndOptimizeCronRemovesStalePeers(): void
    {
        $db = $this->db();
        $prefix = $this->dbCreds()['db_prefix'];

        // The cron maintenance is gated on clean_with_cron, which the installer
        // leaves off — enable it so the script's body actually runs.
        $this->enableCleanWithCron();

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

        $r = $this->runCli('clean-and-optimize.php');
        $this->assertSame(0, $r['exit'], $r['stdout'].$r['stderr']);

        // Cleanup is selective: the stale peer is gone, the fresh one survives.
        $this->assertSame(0, $this->scalar($db, "SELECT COUNT(*) FROM `{$prefix}peers` WHERE `peer_id`='{$stalePeer}'"));
        $this->assertSame(1, $this->scalar($db, "SELECT COUNT(*) FROM `{$prefix}peers` WHERE `peer_id`='{$freshPeer}'"));
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
