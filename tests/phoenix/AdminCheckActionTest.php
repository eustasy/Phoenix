<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class AdminCheckActionTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/controller/admin.check.php';
    }

    public function testReportsSuccessWhenTablesAreSound(): void
    {
        $result = \admin_check_action(self::$connection, self::$settings, self::$time);

        $this->assertSame('All tables checked and reported no errors.', $result);
    }
}
