<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class SettingsLoadTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once __DIR__.'/../../src/functions/function.settings.load.php';
	}

	private string $errorLogBackup;
	private string $errorLogFile;

	/** @var list<string> */
	private array $tmpFiles = [];

	protected function setUp(): void {
		parent::setUp();
		// Redirect error_log() output to a tempfile so the missing-config
		// fallback doesn't leak warnings into PHPUnit's stderr.
		$this->errorLogFile   = tempnam(sys_get_temp_dir(), 'phx_errlog_');
		$this->errorLogBackup = (string)ini_get('error_log');
		ini_set('error_log', $this->errorLogFile);
	}

	protected function tearDown(): void {
		ini_set('error_log', $this->errorLogBackup);
		if ( is_file($this->errorLogFile) ) {
			unlink($this->errorLogFile);
		}
		foreach ( $this->tmpFiles as $f ) {
			if ( is_file($f) ) {
				unlink($f);
			}
		}
		$this->tmpFiles = [];
		parent::tearDown();
	}

	/**
	 * Write a synthetic settings file containing $settings[...] = ... lines.
	 *
	 * @param array<string, mixed> $values
	 */
	private function makeConfigFile(array $values): string {
		$tmp = tempnam(sys_get_temp_dir(), 'phx_settings_');
		$this->tmpFiles[] = $tmp;
		$php = "<?php\n";
		foreach ( $values as $k => $v ) {
			$php .= '$settings['.var_export($k, true).'] = '.var_export($v, true).";\n";
		}
		file_put_contents($tmp, $php);
		return $tmp;
	}

	public function testLoadsDefaultsWhenCustomMissing(): void {
		// Custom path that doesn't exist forces the fallback branch and
		// should still merge in whatever the default file provided.
		$default = $this->makeConfigFile(['announce_interval' => 1800]);
		$custom  = sys_get_temp_dir().'/phoenix_custom_does_not_exist_'.bin2hex(random_bytes(4)).'.php';

		$result = \settings_load($default, $custom);

		// Default value preserved.
		$this->assertSame(1800, $result['announce_interval']);
		// Hard-coded fallbacks applied.
		$this->assertSame('localhost', $result['db_host']);
		$this->assertSame('root',      $result['db_user']);
		$this->assertSame('Password1', $result['db_pass']);
		$this->assertSame('phoenix',   $result['db_name']);
		$this->assertTrue($result['db_persist']);
		$this->assertTrue($result['open_tracker']);
	}

	public function testLogsFallbackMessageWhenCustomMissing(): void {
		// Same fallback scenario, but assert on the error_log output.
		$default = $this->makeConfigFile([]);
		$custom  = sys_get_temp_dir().'/phoenix_custom_does_not_exist_'.bin2hex(random_bytes(4)).'.php';

		\settings_load($default, $custom);

		$logged = file_get_contents($this->errorLogFile);
		$this->assertNotFalse($logged);
		$this->assertStringContainsString('not readable', $logged);
		$this->assertStringContainsString('Falling back to defaults', $logged);
		$this->assertStringContainsString($custom, $logged);
	}

	public function testCustomOverridesDefault(): void {
		// Custom file present. Its values should override the default's
		// for any shared key, while non-shared keys from the default
		// survive and the missing-file fallbacks do NOT fire.
		$default = $this->makeConfigFile([
			'db_host'           => '%db_host%', // matches the real default file's placeholder style
			'db_name'           => '%db_name%',
			'announce_interval' => 1800,
		]);
		$custom = $this->makeConfigFile([
			'db_host' => 'real.example.com',
			'db_name' => 'phoenix_prod',
		]);

		$result = \settings_load($default, $custom);

		$this->assertSame('real.example.com', $result['db_host']);
		$this->assertSame('phoenix_prod',     $result['db_name']);
		// Non-overridden default key survived.
		$this->assertSame(1800, $result['announce_interval']);
		// Missing-file fallbacks should not have been applied.
		$this->assertArrayNotHasKey('db_user', $result);

		$this->assertSame('', (string)file_get_contents($this->errorLogFile));
	}

}
