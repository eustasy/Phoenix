<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/controller/admin.install.php';
require_once __DIR__.'/../../src/functions/install.setup.token.php';

class AdminInstallControllerTest extends PhoenixTestCase
{
    private const CONTROLLER_PATH = __DIR__.'/../../src/controller/admin.install.php';
    private const TEST_PREFIX = 'phoenix_install_test_';

    private int $errorReporting;

    /** @var array<string, mixed> */
    private array $postBackup;

    private string $dir;
    private string $configPath;
    private string $tokenPath;

    protected function setUp(): void
    {
        parent::setUp();
        // admin_install_controller() calls error_reporting(0) internally; save
        // the current level so we can restore it after each test.
        $this->errorReporting = error_reporting();
        $this->postBackup = $_POST;

        // Each test gets an isolated config dir so its setup-token file can't
        // collide with another test's (or leak into the real config/).
        $this->dir = sys_get_temp_dir().'/phxinstall_'.bin2hex(random_bytes(6));
        mkdir($this->dir);
        $this->configPath = $this->dir.'/phoenix.custom.php';
        $this->tokenPath = $this->dir.'/.phoenix-setup-token';
    }

    protected function tearDown(): void
    {
        error_reporting($this->errorReporting);
        $_POST = $this->postBackup;
        @unlink($this->tokenPath);
        @unlink($this->configPath);
        @rmdir($this->dir);
        parent::tearDown();
    }

    /** The current setup token (created if absent), as the operator would read it. */
    private function token(): string
    {
        return install_setup_token($this->tokenPath);
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
        // First-run GET: render the form (including the setup-token field), not
        // a connection attempt.
        $_POST = [];
        $html = \admin_install_controller($this->configPath);

        $this->assertIsString($html);
        $this->assertStringContainsString('name="process" value="install"', $html);
        $this->assertStringContainsString('name="db_prefix" value="phoenix_"', $html);
        $this->assertStringContainsString('name="setup_token"', $html);
    }

    public function testGetCreatesSetupTokenFile(): void
    {
        // Rendering the installer writes the token so the operator can read it.
        $_POST = [];
        \admin_install_controller($this->configPath);

        $this->assertFileExists($this->tokenPath);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{36}$/', trim((string) file_get_contents($this->tokenPath)));
    }

    public function testFormRepopulatesUserSubmittedValues(): void
    {
        $_POST = [
            'db_host' => 'db.example.test',
            'db_user' => 'phoenix_user',
            'db_name' => 'phoenix_db',
        ];
        $html = \admin_install_controller($this->configPath);

        $this->assertIsString($html);
        $this->assertStringContainsString('value="db.example.test"', $html);
        $this->assertStringContainsString('value="phoenix_user"', $html);
        $this->assertStringContainsString('value="phoenix_db"', $html);
    }

    public function testRejectsInstallWithoutSetupToken(): void
    {
        // No token → refused before any DB probe or config write.
        $_POST = ['process' => 'install', 'admin_password' => 'test-admin-pw'];
        $html = \admin_install_controller($this->configPath);

        $this->assertStringContainsString('setup token is incorrect', $html);
        $this->assertFileDoesNotExist($this->configPath);
    }

    public function testRejectsInstallWithWrongSetupToken(): void
    {
        $_POST = ['process' => 'install', 'admin_password' => 'test-admin-pw', 'setup_token' => 'deadbeefdeadbeef'];
        $html = \admin_install_controller($this->configPath);

        $this->assertStringContainsString('setup token is incorrect', $html);
        $this->assertFileDoesNotExist($this->configPath);
    }

    public function testReturnsFormWithErrorOnBadDbCredentials(): void
    {
        // With a valid token, an unreachable DB host re-renders "Could not
        // connect" — proving the token gate is passed and the DB step reached.
        $_POST = [
            'process' => 'install',
            'setup_token' => $this->token(),
            'db_host' => '127.0.0.1:1', // closed port; mysqli_connect will fail
            'db_user' => 'phoenix_test_user',
            'db_pass' => 'phoenix_test_pass',
            'db_name' => 'phoenix_test_db',
            'admin_password' => 'test-admin-pw',
        ];
        $html = \admin_install_controller($this->configPath);

        $this->assertIsString($html);
        $this->assertStringContainsString('Could not connect to the database', $html);
        $this->assertStringContainsString('name="process" value="install"', $html);
    }

    public function testReturnsFormWithErrorWhenConfigDirectoryIsNotWritable(): void
    {
        // A non-existent dir is not writable, so the not-writable branch fires
        // before (and instead of) the token step — no token is created there.
        $configPath = sys_get_temp_dir().'/phoenix_no_such_dir/phoenix.custom.php';

        $_POST = ['process' => 'install', 'admin_password' => 'test-admin-pw'];
        $html = \admin_install_controller($configPath);

        $this->assertIsString($html);
        $this->assertStringContainsString('is not writable', $html);
        $this->assertStringNotContainsString('Could not connect', $html);
        $this->assertStringNotContainsString('<form', $html);
    }

    public function testReturnsFormWithErrorWhenConfigPathCannotBeWritten(): void
    {
        // DB connect + db_create succeed, but file_put_contents fails because the
        // target path is a directory of the same name.
        $configPath = $this->dir.'/collide';
        $this->assertTrue(mkdir($configPath));

        $_POST = [
            'process' => 'install',
            'setup_token' => $this->token(), // token lives at dirname($configPath) == $this->dir
            'db_host' => self::$settings['db_host'],
            'db_user' => self::$settings['db_user'],
            'db_pass' => self::$settings['db_pass'],
            'db_name' => self::$settings['db_name'],
            'db_prefix' => self::TEST_PREFIX,
            'admin_password' => 'test-admin-pw',
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
        // End-to-end happy path. The success branch ends in header() + exit(),
        // which would kill the PHPUnit worker, so it runs in a subprocess. The
        // token is pre-created here so the subprocess can submit a matching one.
        $token = $this->token();

        try {
            $post = [
                'process' => 'install',
                'setup_token' => $token,
                'db_host' => self::$settings['db_host'],
                'db_user' => self::$settings['db_user'],
                'db_pass' => self::$settings['db_pass'],
                'db_name' => self::$settings['db_name'],
                'db_prefix' => self::TEST_PREFIX,
                'admin_password' => 'test-admin-pw',
            ];
            $script = '<?php '.
                '$_POST   = '.var_export($post, true).'; '.
                '$_SERVER["REQUEST_METHOD"] = "POST"; '.
                'require '.var_export(self::CONTROLLER_PATH, true).'; '.
                '$result = admin_install_controller('.var_export($this->configPath, true).'); '.
                'echo "RESULT_TYPE:".gettype($result)."\n"; '.
                'if (is_string($result)) { echo $result; }';
            $result = $this->runPhpSubprocess($script);

            $this->assertSame(0, $result['exit']);
            // Successful redirect path exits before "RESULT_TYPE:" prints.
            $this->assertStringNotContainsString('RESULT_TYPE:', $result['stdout']);

            $this->assertFileExists($this->configPath);
            $contents = file_get_contents($this->configPath);
            $this->assertNotFalse($contents);
            $this->assertStringContainsString(self::TEST_PREFIX, $contents);
            // The token is single-use — consumed once setup completes.
            $this->assertFileDoesNotExist($this->tokenPath);
        } finally {
            $this->dropTestTables();
        }
    }

    public function testRejectsInstallWithoutAdminPassword(): void
    {
        // The password guard fires before the token check, so no token is needed
        // to exercise it.
        $_POST = ['process' => 'install'];
        $html = \admin_install_controller($this->configPath);

        $this->assertIsString($html);
        $this->assertStringContainsString('Set an admin password', $html);
        $this->assertStringContainsString('name="process" value="install"', $html);
        $this->assertFileDoesNotExist($this->configPath);
    }
}
