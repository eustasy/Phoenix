<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../src/controller/admin.apikeys.php';

class AdminApikeysControllerTest extends TestCase
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

        $tmp = tempnam(sys_get_temp_dir(), 'phxkey_');
        $this->assertNotFalse($tmp);
        $this->path = $tmp;
        file_put_contents($this->path, "<?php\n\$settings['db_pass'] = 'filepass';\n");
    }

    protected function tearDown(): void
    {
        $_POST = $this->postBackup;
        $_SESSION = $this->sessionBackup;
        @unlink($this->path);
    }

    /**
     * @param array<string, string> $api_keys
     * @return array<string, mixed>
     */
    private function settings(array $api_keys = []): array
    {
        return [
            'phoenix_version' => 'Phoenix Test v.0',
            'admin_password' => 'hash', // non-empty → CSRF enforced
            'api_keys' => $api_keys,
            'nav_counts' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function readBack(): array
    {
        $settings = [];
        include $this->path;

        return $settings;
    }

    public function testRendersPageWithExistingKeys(): void
    {
        $html = \admin_apikeys_controller($this->settings(['alice' => str_repeat('a', 64)]), $this->path);
        $this->assertStringContainsString('name="process" value="apikey_create"', $html);
        $this->assertStringContainsString('alice', $html);
    }

    public function testCreateStoresHashAndShowsPlaintextOnce(): void
    {
        $_SESSION['phoenix_csrf'] = 'tok';
        $_POST = ['process' => 'apikey_create', 'api_user' => 'alice', 'csrf' => 'tok'];

        $html = \admin_apikeys_controller($this->settings(), $this->path);

        // The plaintext key is shown once in the page.
        $this->assertSame(1, preg_match('/value="(phx_[0-9a-f]{64})"/', $html, $m));
        $shown = $m[1];

        // Only its SHA-256 hash is persisted — the plaintext never touches disk.
        $stored = $this->readBack();
        $this->assertSame(hash('sha256', $shown), $stored['api_keys']['alice']);
        $config = (string) file_get_contents($this->path);
        $this->assertStringContainsString(hash('sha256', $shown), $config);
        $this->assertStringNotContainsString($shown, $config);
    }

    public function testCreateRejectsInvalidUserName(): void
    {
        $_SESSION['phoenix_csrf'] = 'tok';
        $_POST = ['process' => 'apikey_create', 'api_user' => 'Bad User!', 'csrf' => 'tok'];

        $html = \admin_apikeys_controller($this->settings(), $this->path);
        $this->assertStringContainsString('User names may contain', $html);
        $this->assertSame([], $this->readBack()['api_keys'] ?? []);
    }

    public function testCreateRequiresValidCsrf(): void
    {
        $_SESSION['phoenix_csrf'] = 'tok';
        $_POST = ['process' => 'apikey_create', 'api_user' => 'alice', 'csrf' => 'wrong'];

        $html = \admin_apikeys_controller($this->settings(), $this->path);
        $this->assertStringContainsString('Security check failed', $html);
        // Nothing written.
        $this->assertArrayNotHasKey('api_keys', $this->readBack());
    }

    public function testRevokeRemovesTheUsersKey(): void
    {
        $_SESSION['phoenix_csrf'] = 'tok';
        $_POST = ['process' => 'apikey_revoke', 'api_user' => 'alice', 'csrf' => 'tok'];
        $settings = $this->settings(['alice' => str_repeat('a', 64), 'bob' => str_repeat('b', 64)]);

        \admin_apikeys_controller($settings, $this->path);

        $stored = $this->readBack();
        $this->assertArrayNotHasKey('alice', $stored['api_keys']);
        $this->assertArrayHasKey('bob', $stored['api_keys']);
    }

    public function testRotatingAnExistingUserChangesTheHash(): void
    {
        $_SESSION['phoenix_csrf'] = 'tok';
        $old = str_repeat('a', 64);
        $_POST = ['process' => 'apikey_create', 'api_user' => 'alice', 'csrf' => 'tok'];

        $html = \admin_apikeys_controller($this->settings(['alice' => $old]), $this->path);

        $this->assertStringContainsString('rotated', $html);
        $this->assertNotSame($old, $this->readBack()['api_keys']['alice']);
    }
}
