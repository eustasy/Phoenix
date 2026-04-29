<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class MysqliDropTableTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once self::$settings['functions'].'function.mysqli.drop.table.php';
	}

	public function testDropsExistingTable(): void {
		mysqli_query(
			self::$connection,
			'CREATE TABLE `'.self::$settings['db_prefix'].'__TEST__` ( `id` int(10) );'
		);
		$this->assertTrue(drop_table(self::$connection, self::$settings, '__TEST__'));
	}

	public function testDropMissingTableIsNoOp(): void {
		// IF EXISTS in the SQL means dropping a missing table is not an error.
		$this->assertTrue(drop_table(self::$connection, self::$settings, '__TEST_NOT_THERE__'));
	}

}
