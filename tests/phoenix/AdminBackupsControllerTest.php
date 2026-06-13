<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/controller/admin.backups.php';

class AdminBackupsControllerTest extends PhoenixTestCase
{
    /** @var array<string, mixed> */
    private array $postBackup;

    /** @var array<string, mixed> */
    private array $getBackup;

    /** @var array<string, mixed> */
    private array $sessionBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->postBackup = $_POST;
        $this->getBackup = $_GET;
        $this->sessionBackup = $_SESSION ?? [];
        $_POST = [];
        $_GET = [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_POST = $this->postBackup;
        $_GET = $this->getBackup;
        $_SESSION = $this->sessionBackup;
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function settingsWithPassword(): array
    {
        $settings = self::$settings;
        $settings['admin_password'] = 'hash';

        return $settings;
    }

    public function testRendersBackupsPage(): void
    {
        // No password → CSRF disabled, pure render (no backup run).
        $html = \admin_backups_controller(self::$connection, self::$settings, self::$time);

        $this->assertStringContainsString('Backups', $html);
        $this->assertStringContainsString('Run backup now', $html);
    }

    public function testRejectsBackupWithoutCsrf(): void
    {
        $_POST = ['process' => 'backup'];

        $html = \admin_backups_controller(self::$connection, $this->settingsWithPassword(), self::$time);

        $this->assertStringContainsString('Security check failed', $html);
    }

    public function testRunBackupSurfacesEngineErrorWhenDirMissing(): void
    {
        // Valid CSRF so the action runs, but a missing backup_dir makes the
        // engine bail with BACKUP_DIR_NOT_FOUND before needing mysqldump — so
        // this exercises dispatch deterministically.
        $settings = $this->settingsWithPassword();
        $settings['backup_dir'] = sys_get_temp_dir().'/phx_no_such_'.bin2hex(random_bytes(4));
        $_SESSION['phoenix_csrf'] = 'tok';
        $_POST = ['process' => 'backup', 'csrf' => 'tok'];

        $html = \admin_backups_controller(self::$connection, $settings, self::$time);

        $this->assertStringContainsString('BACKUP_DIR_NOT_FOUND', $html);
        $this->assertStringNotContainsString('Security check failed', $html);
    }
}
