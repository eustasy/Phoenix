<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class DbMaintenanceTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/db.maintenance.php';
    }

    public function testEmptyTableListSucceedsWithoutQuerying(): void
    {
        // An empty statement string would make mysqli_multi_query() return
        // false, which would report a failure where there was no work.
        $this->assertTrue(\db_maintenance(self::$connection, self::$settings, 'ANALYZE', []));
    }

    public function testAnalyzeSucceedsOnRealTables(): void
    {
        $this->assertTrue(\db_maintenance(self::$connection, self::$settings, 'ANALYZE', ['peers', 'torrents']));
    }

    public function testOptimizeSucceedsDespiteItsRoutineNote(): void
    {
        // InnoDB always answers OPTIMIZE with "note: Table does not support
        // optimize, doing recreate + analyze instead". A note is not an error.
        $this->assertTrue(\db_maintenance(self::$connection, self::$settings, 'OPTIMIZE', ['peers']));
    }

    public function testCheckSucceedsOnRealTables(): void
    {
        $this->assertTrue(\db_maintenance(self::$connection, self::$settings, 'CHECK', ['torrents']));
    }

    public function testFailureInsideAResultSetIsDetected(): void
    {
        // The whole reason this function exists. A missing table comes back as
        // a row with Msg_type=Error while mysqli_errno() stays 0 and
        // mysqli_multi_query() returns true, so reading the rows is the only
        // way to know the run failed.
        $this->assertFalse(\db_maintenance(self::$connection, self::$settings, 'OPTIMIZE', ['__no_such_table__']));
    }

    public function testAFailureAnywhereInTheListFailsTheRun(): void
    {
        // A good table first, so the failure is genuinely mid-sequence rather
        // than in the statement mysqli_multi_query() reports on directly.
        $this->assertFalse(\db_maintenance(self::$connection, self::$settings, 'CHECK', ['peers', '__no_such_table__']));
    }

    public function testMysqliReportingIsRestored(): void
    {
        // The function turns mysqli's exception mode off while it reads the
        // result sets; it must not leave it that way for the rest of the
        // request. mysqli_report() returns a bool rather than the previous
        // mode, so the only honest check is behavioural: a bad query afterwards
        // must still throw.
        \db_maintenance(self::$connection, self::$settings, 'ANALYZE', ['peers']);

        $this->expectException(\mysqli_sql_exception::class);
        mysqli_query(self::$connection, 'SELECT * FROM `__definitely_not_a_table__`;');
    }
}
