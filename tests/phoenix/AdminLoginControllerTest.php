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

        parent::tearDown();
    }

    public function testReturnsNullWhenNoAdminPasswordSet(): void
    {
        // Empty admin_password disables auth entirely; the controller short-
        // circuits before session_start, so this branch is safe to run
        // directly under PHPUnit and is visible to coverage instrumentation.
        $result = \admin_login_controller(['admin_password' => '']);
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
        ]);
        $this->assertIsString($result);
        $this->assertStringContainsString('Incorrect password.', $result);
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
