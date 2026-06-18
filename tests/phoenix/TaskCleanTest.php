<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class TaskCleanTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/task.clean.php';
    }

    protected function tearDown(): void
    {
        foreach (['tasks', 'task_runs'] as $table) {
            mysqli_query(
                self::$connection,
                'DELETE FROM `'.self::$settings['db_prefix'].$table.'` WHERE `name` = \'rtest\';',
            );
        }
    }

    public function testReturnsTrueOnSuccess(): void
    {
        // task_clean only succeeds if every DELETE succeeds; the function itself
        // removes the rows it inserts during cleanup, so no fixture or teardown is needed.
        $this->assertTrue(task_clean(self::$connection, self::$settings, self::$time));
    }

    public function testRemovesTestPrefixedRows(): void
    {
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'torrents` ( `info_hash` ) '.
            'VALUES (\'__TEST_CLEANUP__\');',
        );
        task_clean(self::$connection, self::$settings, self::$time);

        $result = mysqli_query(
            self::$connection,
            'SELECT * FROM `'.self::$settings['db_prefix'].'torrents` '.
            'WHERE `info_hash` = \'__TEST_CLEANUP__\';',
        );
        $this->assertNotFalse($result);
        $this->assertSame(0, mysqli_num_rows($result));
    }

    public function testLogsCleanRunWithSource(): void
    {
        task_clean(self::$connection, self::$settings, self::$time, 'cron');

        require_once __DIR__.'/../../src/model/tasks.select.php';
        $tasks = \tasks_select(self::$connection, self::$settings);
        $this->assertArrayHasKey('clean', $tasks);
        $this->assertSame('cron', $tasks['clean']['source']);
    }

    public function testHistoryRetentionPrunesOldRunsButKeepsLastRun(): void
    {
        $prefix = self::$settings['db_prefix'];
        $old = self::$time - (10 * 86400);
        // Two history runs of a non-sentinel task: one old, one current.
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.$prefix.'task_runs` (`name`, `value`, `source`) VALUES '.
            '(\'rtest\', '.$old.', \'cron\'), (\'rtest\', '.self::$time.', \'cron\');',
        );
        mysqli_query(
            self::$connection,
            'REPLACE INTO `'.$prefix.'tasks` (`name`, `value`, `source`) VALUES (\'rtest\', '.self::$time.', \'cron\');',
        );

        $settings = self::$settings;
        $settings['task_retention'] = 7; // days

        require_once __DIR__.'/../../src/model/tasks.clean.php';
        $this->assertTrue(tasks_clean(self::$connection, $settings, self::$time));

        // The old history row is pruned; the current one survives.
        $count = mysqli_fetch_assoc(mysqli_query(
            self::$connection,
            'SELECT COUNT(*) AS `c` FROM `'.$prefix.'task_runs` WHERE `name` = \'rtest\';',
        ));
        $this->assertIsArray($count);
        $this->assertSame(1, intval($count['c']));

        // The last-run cache is never time-pruned.
        $last = mysqli_query(
            self::$connection,
            'SELECT `value` FROM `'.$prefix.'tasks` WHERE `name` = \'rtest\';',
        );
        $this->assertNotFalse($last);
        $this->assertSame(1, mysqli_num_rows($last));
    }

}
