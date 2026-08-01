<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/controller/admin.login.php';

class AdminLoginControllerTest extends PhoenixTestCase
{
    private const CONTROLLER_PATH = __DIR__.'/../../src/controller/admin.login.php';

    /** @var array<string, mixed> */
    private array $postBackup;

    /** @var array<string, mixed> */
    private array $sessionBackup;

    /** @var array<string, mixed> */
    private array $serverBackup;

    private string $useCookiesBackup;
    private string $useOnlyCookiesBackup;

    /** @var list<string> Isolated session save_paths to remove on teardown. */
    private array $sessionDirs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->postBackup = $_POST ?? [];
        $this->sessionBackup = $_SESSION ?? [];
        $this->serverBackup = $_SERVER;
        $this->useCookiesBackup = (string)ini_get('session.use_cookies');
        $this->useOnlyCookiesBackup = (string)ini_get('session.use_only_cookies');

        // Disable cookie emission so session_start() inside the controller
        // doesn't try to send Set-Cookie after PHPUnit has already produced
        // output. CLI ignores cookies anyway, so this only affects whether
        // PHP attempts the header() call.
        ini_set('session.use_cookies', '0');
        ini_set('session.use_only_cookies', '0');

        $_POST = [];
        $_SESSION = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/admin.php';
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_destroy();
        }
        // Reset the session id so the next test starts fresh.
        @session_id('');

        $_POST = $this->postBackup;
        $_SESSION = $this->sessionBackup;
        $_SERVER = $this->serverBackup;
        ini_set('session.use_cookies', $this->useCookiesBackup);
        ini_set('session.use_only_cookies', $this->useOnlyCookiesBackup);

        foreach ($this->sessionDirs as $dir) {
            foreach (glob($dir.'/sess_*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
        $this->sessionDirs = [];

        parent::tearDown();
    }

    public function testShowsSetPasswordGateWhenNoPassword(): void
    {
        // Empty admin_password forces a one-time set-password gate instead of
        // serving the panel unauthenticated. The controller short-circuits
        // before session_start, so this runs safely in-process.
        $result = \admin_login_controller([
            'admin_password' => '',
            'phoenix_version' => 'Phoenix Test v.0',
        ]);
        $this->assertIsString($result);
        $this->assertStringContainsString('name="process" value="setup_password"', $result);
        $this->assertStringContainsString('Set an admin password', $result);
    }

    public function testReturnsNullWhenAuthOptionalAndNoPassword(): void
    {
        // The documented opt-out: an operator deliberately running the panel
        // unauthenticated (protected by other means) sets admin_auth_optional,
        // which restores the old skip-auth behaviour.
        $result = \admin_login_controller([
            'admin_password' => '',
            'admin_auth_optional' => true,
        ]);
        $this->assertNull($result);
    }

    public function testReturnsNullWhenAlreadyAuthenticated(): void
    {
        // Subprocess-only: in-process priming of $_SESSION['phoenix_authed']
        // is unreliable across PHP/CI configs — session_set_cookie_params
        // on an active session and a second session_start() can interact
        // with use_strict_mode in ways that drop the pre-seeded values
        // before auth_is_authenticated() runs. A clean subprocess avoids
        // that whole class of issue. Only the trailing `return null` line
        // is uncovered as a result.
        $sessionId = bin2hex(random_bytes(13));
        $settings = ['admin_password' => password_hash('secret', PASSWORD_DEFAULT)];

        $script = '<?php '.
            '$_SERVER["REQUEST_METHOD"] = "POST"; '.
            '$_SERVER["REQUEST_URI"]   = "/admin.php"; '.
            'session_id('.var_export($sessionId, true).'); '.
            'session_start(); '.
            '$_SESSION["phoenix_authed"] = "1"; '.
            'session_write_close(); '.
            'require '.var_export(self::CONTROLLER_PATH, true).'; '.
            '$result = admin_login_controller('.var_export($settings, true).'); '.
            'echo "RESULT_TYPE:".gettype($result)."\n"; '.
            'if (is_string($result)) { echo $result; }';
        $result = $this->runPhpSubprocess($script);

        $this->assertSame(0, $result['exit']);
        $this->assertStringContainsString('RESULT_TYPE:NULL', $result['stdout']);
    }

    public function testReturnsLoginFormWhenNotAuthenticated(): void
    {
        $result = @\admin_login_controller([
            'admin_password' => password_hash('secret', PASSWORD_DEFAULT),
            'phoenix_version' => 'Phoenix Test v.0',
        ]);
        $this->assertIsString($result);
        $this->assertStringContainsString('<form method="POST"', $result);
        $this->assertStringContainsString('name="process" value="login"', $result);
        $this->assertStringNotContainsString('Incorrect password.', $result);
    }

    public function testReturnsLoginFormWithErrorOnWrongPassword(): void
    {
        // process=login + bad password should re-render the form WITH the
        // "Incorrect password." banner, not redirect.
        $_POST = ['process' => 'login', 'password' => 'wrong'];
        $result = @\admin_login_controller([
            'admin_password' => password_hash('secret', PASSWORD_DEFAULT),
            'phoenix_version' => 'Phoenix Test v.0',
            // delay 0 exercises the failed-login throttle path without sleeping.
            'admin_login_delay' => 0,
            'admin_login_delay_max' => 0,
        ]);
        $this->assertIsString($result);
        $this->assertStringContainsString('Incorrect password.', $result);
    }

    ////	Session handling on failed logins

    /**
     * Build a subprocess that runs one failed login against an isolated session
     * save_path, and reports the resulting status code and session-file count.
     */
    private function failedLoginScript(string $savePath, bool $withSession): string
    {
        $settings = [
            'admin_password' => password_hash('secret', PASSWORD_DEFAULT),
            'admin_totp_secret' => '',
            'admin_login_delay' => 2,
            'admin_login_delay_max' => 8,
            'phoenix_version' => 'Phoenix Test v.0',
        ];

        return '<?php '.
            'ini_set("session.save_path", '.var_export($savePath, true).'); '.
            'ini_set("session.use_cookies", "0"); '.
            '$_SERVER["REQUEST_METHOD"] = "POST"; '.
            '$_SERVER["REQUEST_URI"]   = "/admin.php"; '.
            '$_POST = ["process" => "login", "password" => "wrong"]; '.
            ($withSession ? 'session_id(str_repeat("a", 26)); ' : '').
            'require '.var_export(self::CONTROLLER_PATH, true).'; '.
            '$out = admin_login_controller('.var_export($settings, true).'); '.
            'echo "STATUS:".intval(http_response_code())."\n"; '.
            'echo "FILES:".count(glob('.var_export($savePath, true).'."/sess_*") ?: [])."\n"; '.
            'echo "BANNER:".intval(strpos((string)$out, "Incorrect password.") !== false)."\n";';
    }

    public function testFailedLoginWithoutASessionStartsNoneAndDoesNotThrottle(): void
    {
        // A session file is created by session_start() whether or not anything
        // is stored in it, so an anonymous scanner POSTing at admin.php must not
        // cause one — otherwise a brute-force run fills the session directory.
        // With nothing to escalate against there is also no backoff to advertise.
        $dir = $this->makeSessionDir();
        $result = $this->runPhpSubprocess($this->failedLoginScript($dir, false));

        $this->assertSame(0, $result['exit'], $result['stderr']);
        $this->assertStringContainsString('FILES:0', $result['stdout']);
        $this->assertStringContainsString('STATUS:0', $result['stdout']);
        // The login form still renders with its error banner.
        $this->assertStringContainsString('BANNER:1', $result['stdout']);
    }

    public function testFailedLoginWithASessionAdvertisesBackoff(): void
    {
        // A client that carries a session IS tracked, and the escalating delay
        // is advertised as 429 + Retry-After rather than slept through, so the
        // worker is released immediately.
        $dir = $this->makeSessionDir();
        $result = $this->runPhpSubprocess($this->failedLoginScript($dir, true));

        $this->assertSame(0, $result['exit'], $result['stderr']);
        $this->assertStringContainsString('STATUS:429', $result['stdout']);
        $this->assertStringContainsString('FILES:1', $result['stdout']);
        $this->assertStringContainsString('BANNER:1', $result['stdout']);
    }

    private function makeSessionDir(): string
    {
        $dir = sys_get_temp_dir().'/phx_sess_'.bin2hex(random_bytes(6));
        mkdir($dir, 0o700);
        $this->sessionDirs[] = $dir;

        return $dir;
    }

    public function testRedirectsOnSuccessfulLogin(): void
    {
        // Subprocess-only: the success branch ends in session_regenerate_id +
        // header() + exit, which would terminate the PHPUnit worker. The
        // other branches above provide coverage for the shared lines; this
        // just confirms the redirect actually fires.
        $sessionId = bin2hex(random_bytes(13));

        $post = ['process' => 'login', 'password' => 'secret'];
        $settings = ['admin_password' => password_hash('secret', PASSWORD_DEFAULT)];

        $script = '<?php '.
            '$_POST = '.var_export($post, true).'; '.
            '$_SERVER["REQUEST_METHOD"] = "POST"; '.
            '$_SERVER["REQUEST_URI"]   = "/admin.php"; '.
            'session_id('.var_export($sessionId, true).'); '.
            'require '.var_export(self::CONTROLLER_PATH, true).'; '.
            '$result = admin_login_controller('.var_export($settings, true).'); '.
            'echo "RESULT_TYPE:".gettype($result)."\n"; '.
            'if (is_string($result)) { echo $result; }';
        $result = $this->runPhpSubprocess($script);

        $this->assertSame(0, $result['exit']);
        // Successful redirect path exits before "RESULT_TYPE:" prints.
        $this->assertStringNotContainsString('RESULT_TYPE:', $result['stdout']);
        $this->assertStringNotContainsString('<form', $result['stdout']);
    }

}
