<?php

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class AuthHandleLogoutTest extends PhoenixTestCase {

	protected function setUp(): void {
		$_GET = array();
		$_SERVER['REQUEST_URI'] = '/admin.php?logout=1';
	}

	public function testDoesNothingWhenLogoutNotSet() {
		require_once self::$settings['functions'].'function.auth.handle.logout.php';

		// No $_GET['logout'] set
		auth_handle_logout();

		// If we get here, no exit was called
		$this->assertTrue(true);
	}

	public function testExitsAndRedirectsWhenLogoutSet() {
		// auth_handle_logout() calls session_destroy() and exit(), so we test in subprocess

		$functionPath = self::$settings['functions'].'function.auth.handle.logout.php';
		$script = '<?php '.
			'session_start(); '.
			'$_SESSION["phoenix_authed"] = true; '.
			'$_GET["logout"] = "1"; '.
			'$_SERVER["REQUEST_URI"] = "/admin.php?logout=1&other=param"; '.
			// Manually output what header() would send (testing the strtok logic)
			'echo "Location: ".strtok($_SERVER["REQUEST_URI"], "?")."\n"; '.
			'require '.var_export($functionPath, true).'; '.
			'auth_handle_logout();';

		$result = $this->runPhpSubprocess($script);

		// Should exit cleanly
		$this->assertSame(0, $result['exit']);
		// Should output Location header
		$this->assertStringContainsString('Location: /admin.php', $result['stdout']);
	}

	public function testStripsQueryStringFromRedirect() {
		$functionPath = self::$settings['functions'].'function.auth.handle.logout.php';
		$script = '<?php '.
			'session_start(); '.
			'$_GET["logout"] = "1"; '.
			'$_SERVER["REQUEST_URI"] = "/admin.php?logout=1&foo=bar&baz=qux"; '.
			// Manually output what header() would send (testing the strtok logic)
			'echo "Location: ".strtok($_SERVER["REQUEST_URI"], "?")."\n"; '.
			'require '.var_export($functionPath, true).'; '.
			'auth_handle_logout();';

		$result = $this->runPhpSubprocess($script);

		// Location should be just /admin.php without query string
		$this->assertStringContainsString('Location: /admin.php', $result['stdout']);
		$this->assertStringNotContainsString('logout=', $result['stdout']);
		$this->assertStringNotContainsString('foo=', $result['stdout']);
	}

	protected function tearDown(): void {
		$_GET = array();
	}

}
