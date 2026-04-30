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

	public function testReturnsFalseAndEchoesErrorOnSqlFailure(): void {
		// PHP 8.1+ mysqli defaults to throwing on errors; switch reporting off so
		// mysqli_query returns false and the !$result fallback branch is reached.
		mysqli_report(MYSQLI_REPORT_OFF);
		try {
			// A backtick in the table name breaks identifier quoting and forces a syntax error.
			ob_start();
			$result = drop_table(self::$connection, self::$settings, 'bad`name');
			$output = ob_get_clean();
			$this->assertFalse($result);
			$this->assertNotEmpty($output);
		} finally {
			mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
		}
	}

}
