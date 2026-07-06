<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../src/controller/admin.settings.php';

class AdminSettingsControllerTest extends TestCase
{
    private string $path;

    /** @var array<string, mixed> */
    private array $postBackup;

    /** @var array<string, mixed> */
    private array $sessionBackup;

    protected function setUp(): void
    {
        $this->postBackup = $_POST;
        $this->sessionBackup = $_SESSION ?? [];
        $_POST = [];
        $_SESSION = [];

        $tmp = tempnam(sys_get_temp_dir(), 'phxset_');
        $this->assertNotFalse($tmp);
        $this->path = $tmp;
        // Starter custom config the controller will rewrite (preserving these).
        file_put_contents(
            $this->path,
            "<?php\n\$settings['db_pass'] = 'filepass';\n".
            "\$settings['api_keys'] = ['*' => 'adminkey'];\n".
            "\$settings['admin_password'] = 'STARTER';\n",
        );
    }

    protected function tearDown(): void
    {
        $_POST = $this->postBackup;
        $_SESSION = $this->sessionBackup;
        @unlink($this->path);
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        return [
            'phoenix_version' => 'Phoenix Test v.0',
            'admin_password' => 'hash', // non-empty → CSRF enforced
            'db_pass' => 'x',
            'api_keys' => [],
            'open_tracker' => true,
            'public_index' => false,
            'full_scrape' => false,
            'db_reset' => false,
            // The controller probes geo availability for the stats_geo toggle.
            'stats_geo_database' => '',
        ];
    }

    /** @return array<string, mixed> */
    private function readBack(): array
    {
        $settings = [];
        include $this->path;

        return $settings;
    }

    public function testRendersSettingsPage(): void
    {
        $html = \admin_settings_controller($this->settings(), $this->path);
        $this->assertStringContainsString('Settings', $html);
        $this->assertStringContainsString('ph-card-table', $html);
    }

    public function testRejectsPasswordChangeWithoutCsrf(): void
    {
        $_POST = ['process' => 'password', 'new_password' => 'new-password-123'];

        $html = \admin_settings_controller($this->settings(), $this->path);

        $this->assertStringContainsString('Security check failed', $html);
        // The stored password is untouched.
        $this->assertSame('STARTER', $this->readBack()['admin_password']);
    }

    public function testChangesPasswordWithValidCsrf(): void
    {
        $_SESSION['phoenix_csrf'] = 'tok';
        $_POST = ['process' => 'password', 'new_password' => 'new-password-123', 'csrf' => 'tok'];

        $html = \admin_settings_controller($this->settings(), $this->path);

        $this->assertStringContainsString('Admin password changed', $html);
        $stored = $this->readBack();
        $this->assertIsString($stored['admin_password']);
        $this->assertTrue(password_verify('new-password-123', $stored['admin_password']));
        // Other custom keys preserved.
        $this->assertSame('filepass', $stored['db_pass']);
        $this->assertSame(['*' => 'adminkey'], $stored['api_keys']);
    }

    public function testSavesFlagsWithValidCsrf(): void
    {
        $_SESSION['phoenix_csrf'] = 'tok';
        // open_tracker + full_scrape + stats_enabled checked; the rest absent (off).
        $_POST = ['process' => 'settings', 'open_tracker' => '1', 'full_scrape' => '1', 'stats_enabled' => '1', 'csrf' => 'tok'];

        $html = \admin_settings_controller($this->settings(), $this->path);

        $this->assertStringContainsString('Settings saved', $html);
        $stored = $this->readBack();
        $this->assertTrue($stored['open_tracker']);
        $this->assertTrue($stored['full_scrape']);
        $this->assertTrue($stored['stats_enabled']);
        $this->assertFalse($stored['public_index']);
        $this->assertFalse($stored['stats_geo']);
        $this->assertFalse($stored['db_reset']);
        // Preserved.
        $this->assertSame('filepass', $stored['db_pass']);
    }

    public function testReadOnlyWhenDirNotWritable(): void
    {
        // dirname does not exist → is_writable() is false regardless of user.
        $missing = sys_get_temp_dir().'/phx_nodir_'.bin2hex(random_bytes(4)).'/phoenix.custom.php';

        $html = \admin_settings_controller($this->settings(), $missing);

        $this->assertStringContainsString('is not writable', $html);
        $this->assertStringNotContainsString('name="process" value="password"', $html);
    }

    private function skipWithoutAuthenticatron(): void
    {
        if (! class_exists(\eustasy\Authenticatron::class)) {
            $this->markTestSkipped('eustasy/authenticatron not installed.');
        }
    }

    // A 6-digit code guaranteed to be outside the verifier's acceptance window.
    private function wrongCode(string $secret): string
    {
        $window = \eustasy\Authenticatron::getCodesInRange($secret);
        do {
            $wrong = sprintf('%06d', random_int(0, 999999));
        } while (in_array($wrong, $window, true));

        return $wrong;
    }

    public function testEnablesTwoFactorWithValidCode(): void
    {
        $this->skipWithoutAuthenticatron();
        $secret = \eustasy\Authenticatron::makeSecret();
        $_SESSION['phoenix_csrf'] = 'tok';
        $_POST = [
            'process' => 'totp_enable',
            'totp_secret' => $secret,
            'totp_code' => \eustasy\Authenticatron::getCode($secret),
            'csrf' => 'tok',
        ];

        $html = \admin_settings_controller($this->settings(), $this->path);

        $this->assertStringContainsString('Two-factor authentication enabled', $html);
        $stored = $this->readBack();
        $this->assertSame($secret, $stored['admin_totp_secret']);
        // Other custom keys preserved.
        $this->assertSame('filepass', $stored['db_pass']);
    }

    public function testRejectsTwoFactorEnableWithWrongCode(): void
    {
        $this->skipWithoutAuthenticatron();
        $secret = \eustasy\Authenticatron::makeSecret();
        $_SESSION['phoenix_csrf'] = 'tok';
        $_POST = [
            'process' => 'totp_enable',
            'totp_secret' => $secret,
            'totp_code' => $this->wrongCode($secret),
            'csrf' => 'tok',
        ];

        $html = \admin_settings_controller($this->settings(), $this->path);

        $this->assertStringContainsString('code was incorrect', $html);
        // Nothing persisted: the starter config never had the key.
        $this->assertArrayNotHasKey('admin_totp_secret', $this->readBack());
    }

    public function testDisablesTwoFactorWithValidCode(): void
    {
        $this->skipWithoutAuthenticatron();
        $secret = \eustasy\Authenticatron::makeSecret();
        // Pre-enrol in both the config file and the in-memory settings.
        file_put_contents(
            $this->path,
            "<?php\n\$settings['db_pass'] = 'filepass';\n".
            "\$settings['admin_totp_secret'] = '{$secret}';\n",
        );
        $settings = $this->settings();
        $settings['admin_totp_secret'] = $secret;

        $_SESSION['phoenix_csrf'] = 'tok';
        $_POST = [
            'process' => 'totp_disable',
            'totp_code' => \eustasy\Authenticatron::getCode($secret),
            'csrf' => 'tok',
        ];

        $html = \admin_settings_controller($settings, $this->path);

        $this->assertStringContainsString('Two-factor authentication disabled', $html);
        $this->assertSame('', $this->readBack()['admin_totp_secret']);
    }

    public function testRejectsTwoFactorDisableWithWrongCode(): void
    {
        $this->skipWithoutAuthenticatron();
        $secret = \eustasy\Authenticatron::makeSecret();
        $settings = $this->settings();
        $settings['admin_totp_secret'] = $secret;

        $_SESSION['phoenix_csrf'] = 'tok';
        $_POST = [
            'process' => 'totp_disable',
            'totp_code' => $this->wrongCode($secret),
            'csrf' => 'tok',
        ];

        $html = \admin_settings_controller($settings, $this->path);

        $this->assertStringContainsString('code was incorrect', $html);
        // The starter config is untouched — the secret was never cleared.
        $this->assertSame('STARTER', $this->readBack()['admin_password']);
    }
}
