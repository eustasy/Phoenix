<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class DbAnalyzeTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/db.analyze.php';
        require_once __DIR__.'/../../src/model/db.check.php';
    }

    private function runCount(string $task): int
    {
        $row = mysqli_fetch_assoc(mysqli_query(
            self::$connection,
            'SELECT COUNT(*) AS `n` FROM `'.self::$settings['db_prefix'].'task_runs` WHERE `name` = \''.$task.'\';',
        ));

        return is_array($row) ? intval($row['n']) : 0;
    }

    public function testAnalyzeSucceedsAndLogsItsRun(): void
    {
        $before = $this->runCount('analyze');

        $this->assertTrue(\db_analyze(self::$connection, self::$settings, self::$time, 'cron'));
        $this->assertSame($before + 1, $this->runCount('analyze'));
    }

    public function testCheckSucceedsAndLogsItsRun(): void
    {
        $before = $this->runCount('check');

        $this->assertTrue(\db_check(self::$connection, self::$settings, self::$time, 'admin'));
        $this->assertSame($before + 1, $this->runCount('check'));
    }

    public function testAnalyzeRecordsItsSource(): void
    {
        \db_analyze(self::$connection, self::$settings, self::$time, 'cron');

        $row = mysqli_fetch_assoc(mysqli_query(
            self::$connection,
            'SELECT `source` FROM `'.self::$settings['db_prefix'].'task_runs` '.
            'WHERE `name` = \'analyze\' ORDER BY `id` DESC LIMIT 1;',
        ));
        $this->assertIsArray($row);
        $this->assertSame('cron', $row['source']);
    }

    public function testAnalyzeDoesNotRebuild(): void
    {
        // The distinction the split exists for: ANALYZE updates statistics and
        // reclaims nothing, so it must not rebuild the table. A rebuild resets
        // CREATE_TIME; ANALYZE must leave it alone.
        $name = self::$settings['db_prefix'].'peers';
        $read = function () use ($name): string {
            $row = mysqli_fetch_assoc(mysqli_query(
                self::$connection,
                'SELECT `CREATE_TIME` FROM `information_schema`.`TABLES` '.
                'WHERE TABLE_SCHEMA = \''.self::$settings['db_name'].'\' AND TABLE_NAME = \''.$name.'\';',
            ));

            return is_array($row) ? (string) $row['CREATE_TIME'] : '';
        };

        $before = $read();
        \db_analyze(self::$connection, self::$settings, self::$time, 'cron');

        $this->assertSame($before, $read());
    }
}
