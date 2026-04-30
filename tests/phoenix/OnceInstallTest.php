<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class OnceInstallTest extends PhoenixTestCase {

	private string $configPath = '';
	/** @var array<string, mixed> */
	private array $postBackup = array();

	protected function setUp(): void {
		$this->postBackup = $_POST;
		$this->configPath = tempnam(sys_get_temp_dir(), 'phx_install_cfg_');
		// Remove the temp file so the once is the one to (re)create it on success.
		unlink($this->configPath);
	}

	protected function tearDown(): void {
		$_POST = $this->postBackup;
		if ( file_exists($this->configPath) ) {
			unlink($this->configPath);
		}
	}

	/**
	 * Runs the once with the supplied locals. Captures any header()/redirect
	 * by suppressing output buffer; the once never echoes, but headers can
	 * trigger warnings in CLI contexts when output has already been sent.
	 */
	private function runOnce(bool $settings_writable, ?string &$install_error): void {
		$connection  = self::$connection;
		$settings    = self::$settings;
		$time        = self::$time;
		$config_path = $this->configPath;
		require $settings['onces'].'once.install.php';
	}

	public function testGuardSkipsWhenSettingsNotWritable(): void {
		$_POST = array(
			'process' => 'install',
			'db_host' => 'should-not-be-used',
		);
		$install_error = null;

		$this->runOnce(false, $install_error);

		$this->assertNull($install_error);
		$this->assertFileDoesNotExist($this->configPath);
	}

	public function testGuardSkipsWhenProcessNotInstall(): void {
		$_POST = array('process' => 'something_else');
		$install_error = null;

		$this->runOnce(true, $install_error);

		$this->assertNull($install_error);
		$this->assertFileDoesNotExist($this->configPath);
	}

	public function testGuardSkipsWhenProcessNotSet(): void {
		$_POST = array();
		$install_error = null;

		$this->runOnce(true, $install_error);

		$this->assertNull($install_error);
	}

	public function testSetsInstallErrorWhenConnectionFails(): void {
		// Bogus credentials so mysqli_connect fails; the catch in the once
		// converts the strict-mode exception into $test_conn = false.
		$_POST = array(
			'process'    => 'install',
			'db_host'    => '127.0.0.1',
			'db_user'    => '__phx_no_such_user__',
			'db_pass'    => 'wrong',
			'db_name'    => 'phoenix',
			'db_prefix'  => 'phx_',
		);
		$install_error = null;

		$this->runOnce(true, $install_error);

		$this->assertNotNull($install_error);
		$this->assertStringStartsWith('Could not connect to the database', $install_error);
		$this->assertFileDoesNotExist($this->configPath);
	}

}
