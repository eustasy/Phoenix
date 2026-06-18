<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class TaskLogTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/task.log.php';
    }

    protected function tearDown(): void
    {
        foreach (['tasks', 'task_runs'] as $table) {
            mysqli_query(
                self::$connection,
                'DELETE FROM `'.self::$settings['db_prefix'].$table.'` WHERE `name` LIKE \'__TEST_%\';',
            );
        }
    }

    /** @return array<string, string|null> */
    private function fetchLast(): array
    {
        $row = mysqli_fetch_assoc(mysqli_query(
            self::$connection,
            'SELECT `value`, `source` FROM `'.self::$settings['db_prefix'].'tasks` WHERE `name` = \'__TEST__\';',
        ));
        $this->assertIsArray($row);

        return $row;
    }

    private function countHistory(): int
    {
        $row = mysqli_fetch_assoc(mysqli_query(
            self::$connection,
            'SELECT COUNT(*) AS `c` FROM `'.self::$settings['db_prefix'].'task_runs` WHERE `name` = \'__TEST__\';',
        ));

        return intval($row['c']);
    }

    public function testLogsLastRunAndHistoryWithSource(): void
    {
        $this->assertTrue(task_log(self::$connection, self::$settings, '__TEST__', 1, 'admin'));

        $last = $this->fetchLast();
        $this->assertEquals(1, $last['value']);
        $this->assertSame('admin', $last['source']);
        // History gets one row too.
        $this->assertSame(1, $this->countHistory());
    }

    public function testLastRunReplacesButHistoryAppends(): void
    {
        task_log(self::$connection, self::$settings, '__TEST__', 1, 'auto');
        $this->assertTrue(task_log(self::$connection, self::$settings, '__TEST__', 42, 'cron'));

        // tasks keeps only the latest run (REPLACE)...
        $last = $this->fetchLast();
        $this->assertEquals(42, $last['value']);
        $this->assertSame('cron', $last['source']);
        // ...while task_runs accumulates every run (append).
        $this->assertSame(2, $this->countHistory());
    }

    public function testSourceDefaultsToAuto(): void
    {
        task_log(self::$connection, self::$settings, '__TEST__', 5);
        $this->assertSame('auto', $this->fetchLast()['source']);
    }
}
