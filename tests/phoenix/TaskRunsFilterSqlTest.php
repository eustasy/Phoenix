<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class TaskRunsFilterSqlTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/task.runs.filter.sql.php';
    }

    public function testNoFilterIsAnEmptyClause(): void
    {
        $filter = \task_runs_filter_sql();

        $this->assertSame('', $filter['where']);
        $this->assertSame([], $filter['params']);
    }

    public function testNameOnly(): void
    {
        $filter = \task_runs_filter_sql('clean');

        $this->assertSame(' WHERE `name` = ?', $filter['where']);
        $this->assertSame(['clean'], $filter['params']);
    }

    public function testSourceOnly(): void
    {
        $filter = \task_runs_filter_sql('', 'cron');

        $this->assertSame(' WHERE `source` = ?', $filter['where']);
        $this->assertSame(['cron'], $filter['params']);
    }

    public function testBothAreAnded(): void
    {
        $filter = \task_runs_filter_sql('clean', 'auto');

        $this->assertSame(' WHERE `name` = ? AND `source` = ?', $filter['where']);
        $this->assertSame(['clean', 'auto'], $filter['params']);
    }

    public function testValuesAreBoundNeverInterpolated(): void
    {
        // The controller validates against known values, but that is not a
        // reason for the clause to interpolate: the value still arrives from
        // the query string.
        $filter = \task_runs_filter_sql("'; DROP TABLE x; --", "' OR 1=1");

        $this->assertSame(' WHERE `name` = ? AND `source` = ?', $filter['where']);
        $this->assertSame(["'; DROP TABLE x; --", "' OR 1=1"], $filter['params']);
    }
}
