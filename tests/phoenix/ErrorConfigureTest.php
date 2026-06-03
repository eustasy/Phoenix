<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class ErrorConfigureTest extends PhoenixTestCase
{
    private string $displayErrors;

    private string $errorLog;

    private int $errorReporting;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/error.configure.php';
    }

    // error_configure mutates global ini/error_reporting state; snapshot it
    // before each test and restore after so neither the worker nor sibling
    // tests inherit a flipped display_errors / raised reporting level.
    protected function setUp(): void
    {
        parent::setUp();
        $this->displayErrors = (string) ini_get('display_errors');
        $this->errorLog = (string) ini_get('error_log');
        $this->errorReporting = error_reporting();
    }

    protected function tearDown(): void
    {
        ini_set('display_errors', $this->displayErrors);
        ini_set('error_log', $this->errorLog);
        error_reporting($this->errorReporting);
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function settings(array $overrides = []): array
    {
        return array_merge(['debug' => false, 'error_log' => ''], $overrides);
    }

    public function testDebugOffLeavesBaselineUntouched(): void
    {
        // Simulate the bootstrap baseline, then confirm error_configure leaves
        // it alone when debug is off.
        ini_set('display_errors', '0');
        error_reporting(E_ALL & ~E_DEPRECATED);

        error_configure($this->settings(['debug' => false]));

        $this->assertSame('0', (string) ini_get('display_errors'));
        $this->assertSame(E_ALL & ~E_DEPRECATED, error_reporting());
    }

    public function testDebugOnDisplaysErrorsAndRaisesReporting(): void
    {
        ini_set('display_errors', '0');
        error_reporting(E_ALL & ~E_DEPRECATED);

        error_configure($this->settings(['debug' => true]));

        $this->assertSame('1', (string) ini_get('display_errors'));
        $this->assertSame(E_ALL, error_reporting());
    }

    public function testErrorLogPathAppliedWhenSet(): void
    {
        $path = sys_get_temp_dir().'/phoenix_error_configure_test.log';
        error_configure($this->settings(['error_log' => $path]));
        $this->assertSame($path, ini_get('error_log'));
    }

    public function testEmptyErrorLogLeavesDestinationUnchanged(): void
    {
        $before = (string) ini_get('error_log');
        error_configure($this->settings(['error_log' => '']));
        $this->assertSame($before, (string) ini_get('error_log'));
    }
}
