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

    public function testInvalidDownloadShowsNotFound(): void
    {
        // An unknown name must not exit — it sets a message and renders the page.
        $_GET['download'] = 'nope.sql';

        $html = \admin_backups_controller(self::$connection, self::$settings, self::$time);

        $this->assertStringContainsString('Backup not found.', $html);
    }

    public function testValidDownloadStreamsFile(): void
    {
        // Streaming calls header() + readfile() + exit, so run in a subprocess.
        $db_name = self::$settings['db_name'];
        $filename = $db_name.'.20240101_0000.sql';
        $content = '-- known backup content '.bin2hex(random_bytes(4));

        $bootstrapPath = var_export(dirname(__DIR__).'/bootstrap.php', true);
        $controllerPath = var_export(dirname(__DIR__, 2).'/src/controller/admin.backups.php', true);

        $script = '<?php
$tmpDir = sys_get_temp_dir().\'/phx_dl_\'.bin2hex(random_bytes(4)).\'/\';
mkdir($tmpDir, 0700, true);
$filename = '.var_export($filename, true).';
file_put_contents($tmpDir.$filename, '.var_export($content, true).');
$_GET = [\'download\' => $filename, \'page\' => \'backups\'];
$_POST = [];
$_SESSION = [];
require_once '.$bootstrapPath.';
require_once '.$controllerPath.';
$settings = $GLOBALS[\'phoenix_settings\'];
$settings[\'backup_dir\'] = $tmpDir;
admin_backups_controller($GLOBALS[\'phoenix_connection\'], $settings, $GLOBALS[\'phoenix_time\']);
@unlink($tmpDir.$filename);
@rmdir($tmpDir);
';

        $result = $this->runPhpSubprocess($script);

        $this->assertSame(0, $result['exit']);
        $this->assertStringContainsString($content, $result['stdout']);
    }
}
