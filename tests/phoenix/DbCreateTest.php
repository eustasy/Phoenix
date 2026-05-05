<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class DbCreateTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once self::$settings['model'].'db.create.php';
	}

	public function testCreatesTablesOnFirstCall(): void {
		$this->assertTrue(db_create(self::$connection, self::$settings));
		foreach ( array('peers', 'tasks', 'torrents') as $table ) {
			$check = mysqli_query(self::$connection,
				'SELECT TABLE_NAME FROM `information_schema`.`TABLES` '.
				'WHERE TABLE_SCHEMA = \''.self::$settings['db_name'].'\' '.
				'AND TABLE_NAME = \''.self::$settings['db_prefix'].$table.'\';'
			);
			$this->assertNotFalse($check);
			$this->assertSame(1, mysqli_num_rows($check));
		}
	}

	public function testIsIdempotent(): void {
		// IF NOT EXISTS means a second call must be a no-op, not an error.
		$this->assertTrue(db_create(self::$connection, self::$settings));
		$this->assertTrue(db_create(self::$connection, self::$settings));
	}

}
