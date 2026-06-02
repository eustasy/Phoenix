<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/controller/admin.install.php';

class AdminInstallControllerTest extends PhoenixTestCase
{
    private const CONTROLLER_PATH = __DIR__.'/../../src/controller/admin.install.php';
    private const TEST_PREFIX = 'phoenix_install_test_';

    private int $errorReporting;

    /** @var array<string, mixed> */
    private array $postBackup;

    protected function setUp(): void
    {
        parent::setUp();
        // admin_install_controller() calls error_reporting(0) internally; save
        // the current level so we can restore it after each test rather than
        // leaving the worker silently suppressing warnings for later tests.
        $this->errorReporting = error_reporting();
        $this->postBackup = $_POST;
    }

    protected function tearDown(): void
    {
        error_reporting($this->errorReporting);
        $_POST = $this->postBackup;
        parent::tearDown();
    }

    private function dropTestTables(): void
    {
        $cleanup = self::$settings;
        $cleanup['db_prefix'] = self::TEST_PREFIX;
        require_once __DIR__.'/../../src/model/db.drop.php';
        \db_drop_table(self::$connection, $cleanup, 'peers');
        \db_drop_table(self::$connection, $cleanup, 'tasks');
        \db_drop_table(self::$connection, $cleanup, 'torrents');
    }

    public function testReturnsFormWhenNoProcessFlag(): void
    {
        // First-run GET: no $_POST['process'] means render the bare form, not
        // attempt a connection. Run in-process so the branch is visible to
        // the coverage instrumentation.
        $_POST = [];
        $html = \admin_install_controller('/tmp/phoenix_should_not_exist.php');

        $this->assertIsString($html);
        $this->assertStringContainsString('name="process" value="install"', $html);
        // Default prefix populated when the user hasn't typed one yet.
        $this->assertStringContainsString('name="db_prefix" value="phoenix_"', $html);
        // No error banner expected.
        $this->assertStringNotContainsString('background-pomegranate', $html);
    }

    public function testFormRepopulatesUserSubmittedValues(): void
    {
        // After a failed attempt the form should round-trip the user's input
        // (other than db_pass) so they don't have to retype everything.
        $_POST = [
            'db_host' => 'db.example.test',
            'db_user' => 'phoenix_user',
            'db_name' => 'phoenix_db',
        ];
        $html = \admin_install_controller('/tmp/phoenix_should_not_exist.php');

        $this->assertIsString($html);
        $this->assertStringContainsString('value="db.example.test"', $html);
        $this->assertStringContainsString('value="phoenix_user"', $html);
        $this->assertStringContainsString('value="phoenix_db"', $html);
    }

    public function testReturnsFormWithErrorOnBadDbCredentials(): void
    {
        // process=install but unreachable DB host should re-render with the
        // "Could not connect" banner instead of writing the config.
        $_POST = [
            'process' => 'install',
            'db_host' => '127.0.0.1:1', // closed port; mysqli_connect will fail
            'db_user' => 'phoenix_test_user',
            'db_pass' => 'phoenix_test_pass',
            'db_name' => 'phoenix_test_db',
        ];
        $html = \admin_install_controller('/tmp/phoenix_should_not_exist.php');

        $this->assertIsString($html);
        $this->assertStringContainsString('Could not connect to the database', $html);
        // Make sure we re-render the form, not just an error page.
        $this->assertStringContainsString('name="process" value="install"', $html);
    }

    public function testReturnsFormWithErrorWhenConfigDirectoryIsNotWritable(): void
    {
        // dirname() of $config_path drives the writable check, so pointing it
        // at a non-existent directory triggers the not-writable branch
        // without having to chmod a real path. The view renders its own
        // "<code>config/</code> is not writable" warning rather than the
        // $install_error string (which the view ignores when not writable).
        $configPath = sys_get_temp_dir().'/phoenix_no_such_dir/phoenix.custom.php';

        $_POST = ['process' => 'install'];
        $html = \admin_install_controller($configPath);

        $this->assertIsString($html);
        $this->assertStringContainsString('is not writable', $html);
        // Should not have attempted a DB connect, so no "Could not connect"
        // banner and no form should render alongside the warning.
        $this->assertStringNotContainsString('Could not connect', $html);
        $this->assertStringNotContainsString('<form', $html);
    }

    public function testReturnsFormWithErrorWhenConfigPathCannotBeWritten(): void
    {
        // DB connect + db_create succeed, but file_put_contents fails because
        // the target path is occupied by a directory of the same name. Hits
        // the "Connected and created tables, but could not write the
        // configuration file" branch.
        $configPath = sys_get_temp_dir().'/phoenix_install_collide_'.bin2hex(random_bytes(4));
        $this->assertTrue(mkdir($configPath));

        $_POST = [
            'process' => 'install',
            'db_host' => self::$settings['db_host'],
            'db_user' => self::$settings['db_user'],
            'db_pass' => self::$settings['db_pass'],
            'db_name' => self::$settings['db_name'],
            'db_prefix' => self::TEST_PREFIX,
        ];

        try {
            $html = \admin_install_controller($configPath);

            $this->assertIsString($html);
            $this->assertStringContainsString('could not write the configuration file', $html);
        } finally {
            if (is_dir($configPath)) {
                rmdir($configPath);
            }
            $this->dropTestTables();
        }
    }

    public function testWritesConfigAndRedirectsOnSuccessfulInstall(): void
    {
        // End-to-end happy path: real DB credentials, throwaway config target,
        // throwaway prefix so the install does not collide with the suite's
        // other tables. The success branch ends in header() + exit(), which
        // would terminate the PHPUnit worker, so this one stays in a
        // subprocess. The non-exit branches above are what give us coverage.
        $tmpConfig = tempnam(sys_get_temp_dir(), 'phx_install_');
        $this->assertNotFalse($tmpConfig);

        try {
            $post = [
                'process' => 'install',
                'db_host' => self::$settings['db_host'],
                'db_user' => self::$settings['db_user'],
                'db_pass' => self::$settings['db_pass'],
                'db_name' => self::$settings['db_name'],
                'db_prefix' => self::TEST_PREFIX,
            ];
            $script = '<?php '.
                '$_POST   = '.var_export($post, true).'; '.
                '$_SERVER["REQUEST_METHOD"] = "POST"; '.
                'require '.var_export(self::CONTROLLER_PATH, true).'; '.
                '$result = admin_install_controller('.var_export($tmpConfig, true).'); '.
                'echo "RESULT_TYPE:".gettype($result)."\n"; '.
                'if (is_string($result)) { echo $result; }';
            $result = $this->runPhpSubprocess($script);

            $this->assertSame(0, $result['exit']);
            // Successful redirect path exits before "RESULT_TYPE:" prints.
            $this->assertStringNotContainsString('RESULT_TYPE:', $result['stdout']);

            // Config file should now be a valid PHP file with the expected key.
            $this->assertFileExists($tmpConfig);
            $contents = file_get_contents($tmpConfig);
            $this->assertNotFalse($contents);
            $this->assertStringContainsString(self::TEST_PREFIX, $contents);
        } finally {
            if (is_file($tmpConfig)) {
                unlink($tmpConfig);
            }
            $this->dropTestTables();
        }
    }

}
