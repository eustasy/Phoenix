<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class TaskLogTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once self::$settings['model'].'task.log.php';
	}

	protected function tearDown(): void {
		mysqli_query(
			self::$connection,
			'DELETE FROM `'.self::$settings['db_prefix'].'tasks` WHERE `name` LIKE \'__TEST_%\';'
		);
	}

	public function testInsertsRow(): void {
		$this->assertTrue(task_log(self::$connection, self::$settings, '__TEST__', 1));

		$row = mysqli_fetch_assoc(mysqli_query(
			self::$connection,
			'SELECT `value` FROM `'.self::$settings['db_prefix'].'tasks` '.
			'WHERE `name` = \'__TEST__\';'
		));
		$this->assertIsArray($row);
		$this->assertEquals(1, $row['value']);
	}

	public function testReplacesExistingRow(): void {
		task_log(self::$connection, self::$settings, '__TEST__', 1);
		$this->assertTrue(task_log(self::$connection, self::$settings, '__TEST__', 42));

		$row = mysqli_fetch_assoc(mysqli_query(
			self::$connection,
			'SELECT `value` FROM `'.self::$settings['db_prefix'].'tasks` '.
			'WHERE `name` = \'__TEST__\';'
		));
		$this->assertIsArray($row);
		$this->assertEquals(42, $row['value']);
	}

}
