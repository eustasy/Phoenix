<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class TaskCleanTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		self::requireFunction(self::$settings['functions'].'function.task.clean.php');
	}

	public function testReturnsTrueOnSuccess(): void {
		// task_clean only succeeds if every DELETE succeeds; the function itself
		// removes the rows it inserts during cleanup, so no fixture or teardown is needed.
		$this->assertTrue(task_clean(self::$connection, self::$settings, self::$time));
	}

	public function testRemovesTestPrefixedRows(): void {
		mysqli_query(
			self::$connection,
			'INSERT INTO `'.self::$settings['db_prefix'].'torrents` ( `info_hash` ) '.
			'VALUES (\'__TEST_CLEANUP__\');'
		);
		task_clean(self::$connection, self::$settings, self::$time);

		$result = mysqli_query(
			self::$connection,
			'SELECT * FROM `'.self::$settings['db_prefix'].'torrents` '.
			'WHERE `info_hash` = \'__TEST_CLEANUP__\';'
		);
		$this->assertNotFalse($result);
		$this->assertSame(0, mysqli_num_rows($result));
	}

}
