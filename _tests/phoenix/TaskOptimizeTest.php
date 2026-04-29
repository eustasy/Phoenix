<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class TaskOptimizeTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once self::$settings['functions'].'function.task.optimize.php';
	}

	public function testOptimizesDefaultTables(): void {
		// task_optimize touches real tables; there is no clean alternative that
		// preserves coverage, so we rely on idempotency of CHECK/ANALYZE/REPAIR/OPTIMIZE.
		$this->assertTrue(task_optimize(self::$connection, self::$settings, self::$time));
	}

	public function testEmptyTableSetShortCircuits(): void {
		// Regression: with no specific table and defaults disabled, $tables is empty.
		// The function should short-circuit to true rather than passing an empty SQL
		// string to mysqli_multi_query (which would return false).
		$this->assertTrue(task_optimize(self::$connection, self::$settings, self::$time, false, false));
	}

}
