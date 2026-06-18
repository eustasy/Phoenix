<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class TasksSelectTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/tasks.select.php';
    }

    protected function tearDown(): void
    {
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'tasks` WHERE `name` LIKE \'__TEST_%\';',
        );
    }

    private function seedTask(string $name, int $value, string $source = 'cron'): void
    {
        mysqli_query(
            self::$connection,
            'REPLACE INTO `'.self::$settings['db_prefix'].'tasks` (`name`, `value`, `source`) VALUES '.
            '(\''.$name.'\', '.$value.', \''.$source.'\');',
        );
    }

    public function testReturnsSeededTasksWithValueAndSource(): void
    {
        $this->seedTask('__TEST_a', 1700000000, 'admin');
        $this->seedTask('__TEST_b', 1700000100, 'cron');

        $tasks = \tasks_select(self::$connection, self::$settings);

        $this->assertArrayHasKey('__TEST_a', $tasks);
        $this->assertArrayHasKey('__TEST_b', $tasks);
        $this->assertSame(['value' => 1700000000, 'source' => 'admin'], $tasks['__TEST_a']);
        $this->assertSame(['value' => 1700000100, 'source' => 'cron'], $tasks['__TEST_b']);
    }

    public function testValueIsIntegerAndSourcePresent(): void
    {
        // task_log stores the value as a string-typed mysqli column; the model
        // must intval() it so the view can date()-format it.
        $this->seedTask('__TEST_c', 1699999999, 'auto');

        $tasks = \tasks_select(self::$connection, self::$settings);
        $this->assertIsInt($tasks['__TEST_c']['value']);
        $this->assertSame(1699999999, $tasks['__TEST_c']['value']);
        $this->assertSame('auto', $tasks['__TEST_c']['source']);
    }
}
