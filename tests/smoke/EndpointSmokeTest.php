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
}
