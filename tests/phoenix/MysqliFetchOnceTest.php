<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class MysqliFetchOnceTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once self::$settings['model'].'db.fetch.once.php';
	}

	protected function tearDown(): void {
		mysqli_query(
			self::$connection,
			'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` LIKE \'__TEST_%\';'
		);
	}

	public function testReturnsFalseForEmptyResult(): void {
		$sql = 'SELECT `info_hash` FROM `'.self::$settings['db_prefix'].'torrents`;';
		$this->assertFalse(mysqli_fetch_once(self::$connection, $sql));
	}

	public function testReturnsFirstRowAsAssoc(): void {
		mysqli_query(
			self::$connection,
			'INSERT INTO `'.self::$settings['db_prefix'].'torrents` ( `info_hash` ) '.
			'VALUES (\'__TEST_1__\'), (\'__TEST_2__\'), (\'__TEST_3__\');'
		);
		$sql = 'SELECT `info_hash` FROM `'.self::$settings['db_prefix'].'torrents`;';
		$result = mysqli_fetch_once(self::$connection, $sql);
		$this->assertIsArray($result);
		$this->assertArrayHasKey('info_hash', $result);
	}

}
