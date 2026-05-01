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
			'register_shutdown_function(function() { foreach (headers_list() as $h) { echo $h."\n"; } }); '.
			'session_start(); '.
			'$_SESSION["phoenix_authed"] = true; '.
			'$_GET["logout"] = "1"; '.
			'$_SERVER["REQUEST_URI"] = "/admin.php?logout=1&other=param"; '.
			'require '.var_export($functionPath, true).'; '.
			'auth_handle_logout();';

		$tmp = tempnam(sys_get_temp_dir(), 'phx_test_');
		$this->assertNotFalse($tmp);
		file_put_contents($tmp, $script);

		try {
			$proc = proc_open(
				array(PHP_BINARY, $tmp),
				array(
					0 => array('pipe', 'r'),
					1 => array('pipe', 'w'),
					2 => array('pipe', 'w'),
				),
				$pipes
			);
			$this->assertNotFalse($proc);
			fclose($pipes[0]);
			$stdout = stream_get_contents($pipes[1]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			$exit = proc_close($proc);
		} finally {
			unlink($tmp);
		}

		// Should exit cleanly
		$this->assertSame(0, $exit);
		// Should output Location header
		$this->assertStringContainsString('Location: /admin.php', $stdout);
	}

	public function testStripsQueryStringFromRedirect() {
		$functionPath = self::$settings['functions'].'function.auth.handle.logout.php';
		$script = '<?php '.
			'register_shutdown_function(function() { foreach (headers_list() as $h) { echo $h."\n"; } }); '.
			'session_start(); '.
			'$_GET["logout"] = "1"; '.
			'$_SERVER["REQUEST_URI"] = "/admin.php?logout=1&foo=bar&baz=qux"; '.
			'require '.var_export($functionPath, true).'; '.
			'auth_handle_logout();';

		$tmp = tempnam(sys_get_temp_dir(), 'phx_test_');
		$this->assertNotFalse($tmp);
		file_put_contents($tmp, $script);

		try {
			$proc = proc_open(
				array(PHP_BINARY, $tmp),
				array(
					0 => array('pipe', 'r'),
					1 => array('pipe', 'w'),
					2 => array('pipe', 'w'),
				),
				$pipes
			);
			$this->assertNotFalse($proc);
			fclose($pipes[0]);
			$stdout = stream_get_contents($pipes[1]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			$exit = proc_close($proc);
		} finally {
			unlink($tmp);
		}

		// Location should be just /admin.php without query string
		$this->assertStringContainsString('Location: /admin.php', $stdout);
		$this->assertStringNotContainsString('logout=', $stdout);
		$this->assertStringNotContainsString('foo=', $stdout);
	}

	protected function tearDown(): void {
		$_GET = array();
	}

}
