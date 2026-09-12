<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class AdminAnalyzeActionTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/controller/admin.analyze.php';
    }

    public function testReportsSuccess(): void
    {
        $result = \admin_analyze_action(self::$connection, self::$settings, self::$time);

        $this->assertSame('Table statistics have been refreshed.', $result);
    }

    public function testLogsAnAnalyzeRun(): void
    {
        $count = function (): int {
            $row = mysqli_fetch_assoc(mysqli_query(
                self::$connection,
                'SELECT COUNT(*) AS `n` FROM `'.self::$settings['db_prefix'].'task_runs` WHERE `name` = \'analyze\';',
            ));

            return is_array($row) ? intval($row['n']) : 0;
        };

        $before = $count();
        \admin_analyze_action(self::$connection, self::$settings, self::$time);

        $this->assertSame($before + 1, $count());
    }
}
