<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class AdminInstallControllerTest extends PhoenixTestCase {

	private const CONTROLLER_PATH = __DIR__.'/../../src/controller/admin.install.php';

	/**
	 * Drive admin_install_controller in a subprocess. The successful-install
	 * path calls exit() after redirect, and the controller calls
	 * error_reporting(0) which we'd rather not leak into the PHPUnit worker.
	 *
	 * @param array<string, string> $post
	 * @param array<string, mixed> $settings
	 * @return array{stdout: string, stderr: string, exit: int}
	 */
	private function runInstall(array $post, string $configPath, array $settings = []): array {
		$script = '<?php '.
			'$_POST   = '.var_export($post, true).'; '.
			'$_SERVER["REQUEST_METHOD"] = "POST"; '.
			'require '.var_export(self::CONTROLLER_PATH, true).'; '.
			'$result = admin_install_controller('.var_export($settings, true).', '.var_export($configPath, true).'); '.
			'echo "RESULT_TYPE:".gettype($result)."\n"; '.
			'if (is_string($result)) { echo $result; }';
		return $this->runPhpSubprocess($script);
	}

	public function testReturnsFormWhenNoProcessFlag(): void {
		// First-run GET: no $_POST['process'] means render the bare form, not
		// attempt a connection.
		$result = $this->runInstall([], '/tmp/phoenix_should_not_exist.php');
		$this->assertSame(0, $result['exit']);
		$this->assertStringContainsString('RESULT_TYPE:string', $result['stdout']);
		$this->assertStringContainsString('name="process" value="install"', $result['stdout']);
		// No error banner expected.
		$this->assertStringNotContainsString('background-pomegranate', $result['stdout']);
	}

	public function testReturnsFormWithErrorOnBadDbCredentials(): void {
		// process=install but unreachable DB host should re-render with the
		// "Could not connect" banner.
		$result = $this->runInstall(
			[
				'process' => 'install',
				'db_host' => '127.0.0.1:1', // closed port; mysqli_connect will fail
				'db_user' => 'phoenix_test_user',
				'db_pass' => 'phoenix_test_pass',
				'db_name' => 'phoenix_test_db',
			],
			'/tmp/phoenix_should_not_exist.php'
		);
		$this->assertSame(0, $result['exit']);
		$this->assertStringContainsString('RESULT_TYPE:string', $result['stdout']);
		$this->assertStringContainsString('Could not connect to the database', $result['stdout']);
	}

	public function testWritesConfigAndRedirectsOnSuccessfulInstall(): void {
		// End-to-end happy path: real DB credentials, throwaway config target,
		// throwaway prefix so the install does not collide with the suite's
		// other tables. Controller exits after writing the file.
		$tmpConfig = tempnam(sys_get_temp_dir(), 'phx_install_');
		$this->assertNotFalse($tmpConfig);

		try {
			$result = $this->runInstall(
				[
					'process'   => 'install',
					'db_host'   => self::$settings['db_host'],
					'db_user'   => self::$settings['db_user'],
					'db_pass'   => self::$settings['db_pass'],
					'db_name'   => self::$settings['db_name'],
					'db_prefix' => 'phoenix_install_test_',
				],
				$tmpConfig
			);

			$this->assertSame(0, $result['exit']);
			// Successful redirect path exits before "RESULT_TYPE:" prints.
			$this->assertStringNotContainsString('RESULT_TYPE:', $result['stdout']);

			// Config file should now be a valid PHP file with the expected key.
			$this->assertFileExists($tmpConfig);
			$contents = file_get_contents($tmpConfig);
			$this->assertNotFalse($contents);
			$this->assertStringContainsString('phoenix_install_test_', $contents);
		} finally {
			if ( is_file($tmpConfig) ) {
				unlink($tmpConfig);
			}
			// Drop the throwaway tables so they do not stick around.
			$cleanup              = self::$settings;
			$cleanup['db_prefix'] = 'phoenix_install_test_';
			require_once __DIR__.'/../../src/model/db.drop.php';
			db_drop_table(self::$connection, $cleanup, 'peers');
			db_drop_table(self::$connection, $cleanup, 'tasks');
			db_drop_table(self::$connection, $cleanup, 'torrents');
		}
	}

}
