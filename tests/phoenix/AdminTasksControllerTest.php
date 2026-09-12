<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class AdminTasksControllerTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/controller/admin.tasks.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_GET = [];
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        $settings = self::$settings;
        $settings['admin_password'] = '';

        return $settings;
    }

    public function testRendersThePage(): void
    {
        $html = \admin_tasks_controller(self::$connection, $this->settings());

        $this->assertStringContainsString('Task History', $html);
    }

    public function testUnknownTaskNameFallsBackToAllTasks(): void
    {
        // An unrecognised ?name must not reach the query as a filter value —
        // it widens to "all tasks" rather than filtering to nothing silently.
        $_GET['name'] = 'definitely-not-a-task';

        $html = \admin_tasks_controller(self::$connection, $this->settings());

        $this->assertStringContainsString('value="" selected', $html);
    }

    public function testKnownTaskNameIsKept(): void
    {
        $_GET['name'] = 'backup';

        $html = \admin_tasks_controller(self::$connection, $this->settings());

        $this->assertStringContainsString('value="backup" selected', $html);
    }

    public function testNegativeOffsetIsClamped(): void
    {
        $_GET['offset'] = '-500';

        $html = \admin_tasks_controller(self::$connection, $this->settings());

        // Renders rather than erroring, and never pages below zero.
        $this->assertStringContainsString('Task History', $html);
        $this->assertStringNotContainsString('offset=-', $html);
    }

    public function testKnownTriggerIsKept(): void
    {
        $_GET['source'] = 'cron';

        $html = \admin_tasks_controller(self::$connection, $this->settings());

        $this->assertStringContainsString('value="cron" selected', $html);
    }

    public function testUnknownTriggerWidensBackToAny(): void
    {
        // An unrecognised ?source must not reach the query — it would match
        // nothing and read as "no maintenance has ever run".
        $_GET['source'] = "' OR 1=1 --";

        $html = \admin_tasks_controller(self::$connection, $this->settings());

        $this->assertStringContainsString('>Any trigger</option>', $html);
        $this->assertStringNotContainsString('OR 1=1', $html);
    }

    public function testBothFiltersApplyTogether(): void
    {
        $_GET['name'] = 'clean';
        $_GET['source'] = 'auto';

        $html = \admin_tasks_controller(self::$connection, $this->settings());

        $this->assertStringContainsString('value="clean" selected', $html);
        $this->assertStringContainsString('value="auto" selected', $html);
    }
}
