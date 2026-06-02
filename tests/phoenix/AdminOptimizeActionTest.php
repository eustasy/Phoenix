<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class AdminOptimizeActionTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/controller/admin.optimize.php';
    }

    public function testReturnsSuccessMessageOnOptimizeSuccess(): void
    {
        // db_optimize runs OPTIMIZE TABLE on the prefixed tables; against the
        // healthy test DB it should succeed and route to the success message.
        $result = admin_optimize_action(self::$connection, self::$settings, self::$time);
        $this->assertSame('Your MySQL Tracker Database has been optimized.', $result);
    }

    public function testReturnsFailureMessageWhenOptimizeFails(): void
    {
        // CHECK/REPAIR/etc against a missing table return an error row (not a
        // SQL failure), so a missing prefix would still report success. Force
        // a real syntax error instead by injecting a backtick into the prefix
        // so identifier quoting breaks. Silence mysqli_report so the failed
        // statement returns false instead of throwing.
        $brokenSettings = self::$settings;
        $brokenSettings['db_prefix'] = 'bad`prefix_';

        mysqli_report(MYSQLI_REPORT_OFF);
        try {
            $result = admin_optimize_action(self::$connection, $brokenSettings, self::$time);
            $this->assertSame('Could not optimize the MySQL Database.', $result);
        } finally {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        }
    }

}
