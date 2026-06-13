<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/controller/api.index.php';

class ApiIndexControllerTest extends PhoenixTestCase
{
    private int $errorReporting;

    /** @var array<string, mixed> */
    private array $getBackup;

    protected function setUp(): void
    {
        parent::setUp();
        // Suppress the "headers already sent" warning the controller's header()
        // calls emit under PHPUnit, and preserve $_GET across tests.
        $this->errorReporting = error_reporting();
        $this->getBackup = $_GET;
        error_reporting(0);
    }

    protected function tearDown(): void
    {
        error_reporting($this->errorReporting);
        $_GET = $this->getBackup;
        parent::tearDown();
    }

    public function testReturnsVersionAsJsonByDefault(): void
    {
        $_GET = [];

        $decoded = json_decode(\api_index_controller(self::$settings), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('phoenix', $decoded);
        $this->assertSame(self::$settings['phoenix_version'], $decoded['phoenix']['version']);
    }

    public function testReturnsVersionAsXmlWhenFlagSet(): void
    {
        $_GET = ['xml' => '1'];

        $xml = \api_index_controller(self::$settings);
        $this->assertStringStartsWith('<?xml', $xml);

        $doc = simplexml_load_string($xml);
        $this->assertNotFalse($doc);
        $this->assertSame(self::$settings['phoenix_version'], (string) $doc->version);
    }
}
