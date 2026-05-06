<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class AdminLoginControllerTest extends PhoenixTestCase {

	private const CONTROLLER_PATH = __DIR__.'/../../src/controller/admin.login.php';

	/**
	 * Drive admin_login_controller in a subprocess. The controller calls
	 * session_start() and may exit() after a successful login redirect, so
	 * running it in-process would either trip "headers already sent" or kill
	 * the PHPUnit worker.
	 *
	 * @param array<string, mixed> $settings
	 * @param array<string, string> $post
	 * @param array<string, string> $session
	 * @return array{stdout: string, stderr: string, exit: int}
	 */
	private function runLogin(array $settings, array $post = [], array $session = []): array {
		// Pin a known session id so the seeding session_start() and the
		// controller's session_start() share the same on-disk file. CLI has no
		// cookies, so without this each call would generate a new id and the
		// pre-seeded $_SESSION values would be lost.
		$sessionId = bin2hex(random_bytes(13));

		$script = '<?php '.
			'$_POST = '.var_export($post, true).'; '.
			'$_SERVER["REQUEST_METHOD"] = "POST"; '.
			'$_SERVER["REQUEST_URI"]   = "/admin.php"; '.
			'session_id('.var_export($sessionId, true).'); '.
			'if ('.var_export(!empty($session), true).') { '.
			'  session_start(); '.
			'  foreach ('.var_export($session, true).' as $k => $v) { $_SESSION[$k] = $v; } '.
			'  session_write_close(); '.
			'} '.
			'require '.var_export(self::CONTROLLER_PATH, true).'; '.
			'$result = admin_login_controller('.var_export($settings, true).'); '.
			'echo "RESULT_TYPE:".gettype($result)."\n"; '.
			'if (is_string($result)) { echo $result; }';
		return $this->runPhpSubprocess($script);
	}

	public function testReturnsNullWhenNoAdminPasswordSet(): void {
		// Empty admin_password disables auth entirely; the controller short-
		// circuits before session_start, so no cookie or login form is sent.
		$result = $this->runLogin(['admin_password' => '']);
		$this->assertSame(0, $result['exit']);
		$this->assertStringContainsString('RESULT_TYPE:NULL', $result['stdout']);
	}

	public function testReturnsNullWhenAlreadyAuthenticated(): void {
		// Pre-seeded $_SESSION['phoenix_authed'] = true should skip the login
		// form entirely.
		$result = $this->runLogin(
			['admin_password' => password_hash('secret', PASSWORD_DEFAULT)],
			[],
			['phoenix_authed' => '1']
		);
		$this->assertSame(0, $result['exit']);
		$this->assertStringContainsString('RESULT_TYPE:NULL', $result['stdout']);
	}

	public function testReturnsLoginFormWhenNotAuthenticated(): void {
		$result = $this->runLogin(
			['admin_password' => password_hash('secret', PASSWORD_DEFAULT)],
			[],
			[]
		);
		$this->assertSame(0, $result['exit']);
		$this->assertStringContainsString('RESULT_TYPE:string', $result['stdout']);
		$this->assertStringContainsString('<form method="POST"', $result['stdout']);
		$this->assertStringContainsString('name="process" value="login"', $result['stdout']);
		$this->assertStringNotContainsString('Incorrect password.', $result['stdout']);
	}

	public function testReturnsLoginFormWithErrorOnWrongPassword(): void {
		// process=login + bad password should re-render the form WITH the
		// "Incorrect password." banner, not redirect.
		$result = $this->runLogin(
			['admin_password' => password_hash('secret', PASSWORD_DEFAULT)],
			['process' => 'login', 'password' => 'wrong'],
			[]
		);
		$this->assertSame(0, $result['exit']);
		$this->assertStringContainsString('RESULT_TYPE:string', $result['stdout']);
		$this->assertStringContainsString('Incorrect password.', $result['stdout']);
	}

	public function testRedirectsOnSuccessfulLogin(): void {
		// Correct password → session_regenerate_id + auth_set_authenticated +
		// header(Location) + exit. The exit means execution ends BEFORE the
		// "RESULT_TYPE:" line is reached.
		$result = $this->runLogin(
			['admin_password' => password_hash('secret', PASSWORD_DEFAULT)],
			['process' => 'login', 'password' => 'secret'],
			[]
		);
		$this->assertSame(0, $result['exit']);
		$this->assertStringNotContainsString('RESULT_TYPE:', $result['stdout']);
		$this->assertStringNotContainsString('<form', $result['stdout']);
	}

}
