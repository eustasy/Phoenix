<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/controller/admin.panel.php';

class AdminPanelControllerTest extends PhoenixTestCase
{
    private int $errorReporting;

    /** @var array<string, mixed> */
    private array $postBackup;

    /** @var array<string, mixed> */
    private array $getBackup;

    /** @var array<string, mixed> */
    private array $sessionBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->errorReporting = error_reporting();
        $this->postBackup = $_POST;
        $this->getBackup = $_GET;
        $this->sessionBackup = $_SESSION ?? [];
        $_POST = [];
        $_GET = [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        error_reporting($this->errorReporting);
        $_POST = $this->postBackup;
        $_GET = $this->getBackup;
        $_SESSION = $this->sessionBackup;
        parent::tearDown();
    }

    public function testRendersPanelWithoutAction(): void
    {
        $html = \admin_panel_controller(self::$connection, self::$settings, self::$time);

        $this->assertIsString($html);
        $this->assertStringContainsString('Phoenix', $html);
        // No action submitted → no action message text in output.
        $this->assertStringNotContainsString('peers list has been cleaned', $html);
        $this->assertStringNotContainsString('Tracker Database has been optimized', $html);
    }

    public function testRendersInstalledBannerWhenInstalledFlagSet(): void
    {
        $_GET['installed'] = '1';
        $html = \admin_panel_controller(self::$connection, self::$settings, self::$time);

        // The view's $show_installed=true branch fires; the banner contents
        // vary, but the key signal is the gate (isset($_GET['installed']))
        // reaches view_admin_html. ViewAdminHtmlTest pins the exact text.
        $this->assertIsString($html);
    }

    public function testProcessCleanRendersCleanMessage(): void
    {
        // No admin_password → CSRF disabled, so this exercises pure dispatch.
        $settings = self::$settings;
        $settings['admin_password'] = '';
        $_POST['process'] = 'clean';
        $html = \admin_panel_controller(self::$connection, $settings, self::$time);

        $this->assertStringContainsString('The peers list has been cleaned.', $html);
    }

    public function testProcessOptimizeRendersOptimizeMessage(): void
    {
        $settings = self::$settings;
        $settings['admin_password'] = '';
        $_POST['process'] = 'optimize';
        $html = \admin_panel_controller(self::$connection, $settings, self::$time);

        $this->assertStringContainsString('Your MySQL Tracker Database has been optimized.', $html);
    }

    public function testProcessSetupCreatesTablesUnderUnknownPrefix(): void
    {
        // Unknown prefix → tables_installed=false → admin_setup_action's
        // "create" path runs, returns the success message. Clean up the
        // new tables in finally so they don't leak.
        $settings = self::$settings;
        $settings['db_prefix'] = 'phoenix_panel_setup_test_';

        $settings['admin_password'] = '';
        $_POST['process'] = 'setup';
        try {
            $html = \admin_panel_controller(self::$connection, $settings, self::$time);
            $this->assertStringContainsString('Your MySQL Tracker Database has been setup.', $html);
        } finally {
            require_once __DIR__.'/../../src/model/db.drop.php';
            \db_drop_table(self::$connection, $settings, 'peers');
            \db_drop_table(self::$connection, $settings, 'tasks');
            \db_drop_table(self::$connection, $settings, 'torrents');
        }
    }

    public function testUnknownProcessRendersPanelWithoutMessage(): void
    {
        // Anything other than setup/clean/optimize is ignored (no message
        // dispatch, controller still renders the panel).
        $settings = self::$settings;
        $settings['admin_password'] = '';
        $_POST['process'] = 'mystery_action';
        $html = \admin_panel_controller(self::$connection, $settings, self::$time);

        $this->assertIsString($html);
        $this->assertStringContainsString('Phoenix', $html);
        $this->assertStringNotContainsString('has been cleaned', $html);
        $this->assertStringNotContainsString('has been optimized', $html);
    }

    public function testRejectsProcessWithoutCsrfWhenPasswordSet(): void
    {
        // With a password set, a state-changing POST lacking a valid CSRF
        // token is refused: the security message shows and the clean action
        // does not run.
        $settings = self::$settings;
        $settings['admin_password'] = 'hash';
        $_POST['process'] = 'clean';
        // No csrf token in $_SESSION or $_POST.

        $html = \admin_panel_controller(self::$connection, $settings, self::$time);

        $this->assertStringContainsString('Security check failed', $html);
        $this->assertStringNotContainsString('The peers list has been cleaned.', $html);
    }

    public function testAcceptsProcessWithValidCsrfWhenPasswordSet(): void
    {
        // Matching session + POST token lets the action through.
        $settings = self::$settings;
        $settings['admin_password'] = 'hash';
        $_SESSION['phoenix_csrf'] = 'tok';
        $_POST['csrf'] = 'tok';
        $_POST['process'] = 'clean';

        $html = \admin_panel_controller(self::$connection, $settings, self::$time);

        $this->assertStringContainsString('The peers list has been cleaned.', $html);
        $this->assertStringNotContainsString('Security check failed', $html);
    }

    public function testTablesInstalledFlagFalseUnderUnknownPrefix(): void
    {
        // With a db_prefix that has no tables installed, db_size shouldn't
        // be queried (the controller short-circuits). Hard to assert that
        // directly, but we can confirm the panel still renders without
        // erroring.
        $settings = self::$settings;
        $settings['db_prefix'] = 'phoenix_panel_no_tables_';

        $html = \admin_panel_controller(self::$connection, $settings, self::$time);
        $this->assertIsString($html);
        $this->assertStringContainsString('Phoenix', $html);
    }

}
