<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class MysqliArrayBuildTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once self::$settings['model'].'db.fetch.array.php';
	}

	protected function tearDown(): void {
		mysqli_query(
			self::$connection,
			'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` LIKE \'__TEST_%\';'
		);
	}

	public function testEmptyResultIsEmptyArray(): void {
		$select = 'SELECT `info_hash` FROM `'.self::$settings['db_prefix'].'torrents`;';
		$result = mysqli_array_build(self::$connection, $select);
		$this->assertSame(array(), $result);
	}

	public function testReturnsRowsAsFlatArray(): void {
		mysqli_query(
			self::$connection,
			'INSERT INTO `'.self::$settings['db_prefix'].'torrents` ( `info_hash` ) '.
			'VALUES (\'__TEST_1__\'), (\'__TEST_2__\'), (\'__TEST_3__\');'
		);
		$select = 'SELECT `info_hash` FROM `'.self::$settings['db_prefix'].'torrents`;';
		$result = mysqli_array_build(self::$connection, $select);
		$this->assertCount(3, $result);
		$this->assertContains('__TEST_1__', $result);
		$this->assertContains('__TEST_2__', $result);
		$this->assertContains('__TEST_3__', $result);
	}

}
