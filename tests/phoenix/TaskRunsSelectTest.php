<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class TaskRunsSelectTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/task.runs.select.php';
        require_once __DIR__.'/../../src/model/task.runs.count.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->clear();
    }

    protected function tearDown(): void
    {
        $this->clear();
        parent::tearDown();
    }

    private function clear(): void
    {
        mysqli_query(self::$connection, 'DELETE FROM `'.self::$settings['db_prefix'].'task_runs` WHERE `name` LIKE \'t_%\';');
    }

    private function seed(string $name, int $value, string $source): void
    {
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'task_runs` (`name`, `value`, `source`) '.
            'VALUES (\''.$name.'\', '.$value.', \''.$source.'\');',
        );
    }

    public function testReturnsRunsNewestFirst(): void
    {
        $this->seed('t_clean', 1000, 'cron');
        $this->seed('t_clean', 3000, 'admin');
        $this->seed('t_clean', 2000, 'auto');

        $runs = \task_runs_select(self::$connection, self::$settings, 't_clean');

        $this->assertSame([3000, 2000, 1000], array_column($runs, 'value'));
        $this->assertSame('admin', $runs[0]['source']);
    }

    public function testFiltersByName(): void
    {
        $this->seed('t_clean', 1000, 'cron');
        $this->seed('t_backup', 2000, 'cron');

        $runs = \task_runs_select(self::$connection, self::$settings, 't_backup');

        $this->assertCount(1, $runs);
        $this->assertSame('t_backup', $runs[0]['name']);
    }

    public function testEmptyNameReturnsEveryTask(): void
    {
        $this->seed('t_clean', 1000, 'cron');
        $this->seed('t_backup', 2000, 'cron');

        $names = array_column(\task_runs_select(self::$connection, self::$settings), 'name');

        $this->assertContains('t_clean', $names);
        $this->assertContains('t_backup', $names);
    }

    public function testPagesWithLimitAndOffset(): void
    {
        foreach ([1000, 2000, 3000] as $value) {
            $this->seed('t_clean', $value, 'cron');
        }

        $first = \task_runs_select(self::$connection, self::$settings, 't_clean', 2, 0);
        $second = \task_runs_select(self::$connection, self::$settings, 't_clean', 2, 2);

        $this->assertSame([3000, 2000], array_column($first, 'value'));
        $this->assertSame([1000], array_column($second, 'value'));
    }

    public function testRunsSharingATimestampAreOrderedDeterministically(): void
    {
        // One cron pass logs a clean and an optimize in the same second, so
        // `value` alone is not a total order — id breaks the tie.
        $this->seed('t_clean', 5000, 'cron');
        $this->seed('t_clean', 5000, 'cron');

        $ids = array_column(\task_runs_select(self::$connection, self::$settings, 't_clean'), 'id');

        $this->assertGreaterThan($ids[1], $ids[0]);
    }

    public function testCountMatchesTheFilter(): void
    {
        $this->seed('t_clean', 1000, 'cron');
        $this->seed('t_clean', 2000, 'cron');
        $this->seed('t_backup', 3000, 'cron');

        $this->assertSame(2, \task_runs_count(self::$connection, self::$settings, 't_clean'));
        $this->assertSame(1, \task_runs_count(self::$connection, self::$settings, 't_backup'));
        $this->assertSame(0, \task_runs_count(self::$connection, self::$settings, 't_missing'));
    }

    public function testLimitIsClamped(): void
    {
        $this->seed('t_clean', 1000, 'cron');

        // A caller cannot ask for an unbounded or negative page.
        $this->assertCount(1, \task_runs_select(self::$connection, self::$settings, 't_clean', 0));
        $this->assertCount(1, \task_runs_select(self::$connection, self::$settings, 't_clean', -5));
        $this->assertCount(1, \task_runs_select(self::$connection, self::$settings, 't_clean', 99999));
    }
}
