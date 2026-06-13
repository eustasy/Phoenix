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

    private function seedTask(string $name, int $value): void
    {
        mysqli_query(
            self::$connection,
            'REPLACE INTO `'.self::$settings['db_prefix'].'tasks` (`name`, `value`) VALUES '.
            '(\''.$name.'\', '.$value.');',
        );
    }

    public function testReturnsSeededTasksAsNameValueMap(): void
    {
        $this->seedTask('__TEST_a', 1700000000);
        $this->seedTask('__TEST_b', 1700000100);

        $tasks = \tasks_select(self::$connection, self::$settings);

        $this->assertArrayHasKey('__TEST_a', $tasks);
        $this->assertArrayHasKey('__TEST_b', $tasks);
        $this->assertSame(1700000000, $tasks['__TEST_a']);
        $this->assertSame(1700000100, $tasks['__TEST_b']);
    }

    public function testValuesAreIntegers(): void
    {
        // task_log stores the value as a string-typed mysqli column; the model
        // must intval() it so the view can date()-format it.
        $this->seedTask('__TEST_c', 1699999999);

        $tasks = \tasks_select(self::$connection, self::$settings);
        $this->assertIsInt($tasks['__TEST_c']);
        $this->assertSame(1699999999, $tasks['__TEST_c']);
    }
}
