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

    //// bin/ cron entry points (CLI, not HTTP)

    #[Depends('testInstallSucceeds')]
    public function testCleanAndOptimizeCronRuns(): void
    {
        // Bootstraps against the installed config + DB and runs to completion.
        // (clean_with_cron is off by default, so the body is a no-op — this
        // smokes the cron entry point's bootstrap, which announce.php can't.)
        $r = $this->runCli('clean-and-optimize.php');
        $this->assertSame(0, $r['exit'], $r['stdout'].$r['stderr']);
    }

    #[Depends('testInstallSucceeds')]
    public function testBackupDatabaseCronRuns(): void
    {
        $root = dirname(__DIR__, 2);
        @mkdir($root.'/backups');

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
    }
}
