<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class PeerCompletedTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once self::$settings['functions'].'function.peer.completed.php';
	}

	protected function tearDown(): void {
		mysqli_query(
			self::$connection,
			'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` LIKE \'__TEST_%\';'
		);
	}

	public function testInsertsRowWithDownloadCountOne(): void {
		$peer = array('info_hash' => '__TEST_1__');
		$this->assertTrue(peer_completed(self::$connection, self::$settings, $peer));

		$row = mysqli_fetch_assoc(mysqli_query(
			self::$connection,
			'SELECT `downloads` FROM `'.self::$settings['db_prefix'].'torrents` '.
			'WHERE `info_hash` = \'__TEST_1__\';'
		));
		$this->assertIsArray($row);
		$this->assertEquals(1, $row['downloads']);
	}

	public function testIncrementsExistingRow(): void {
		$peer = array('info_hash' => '__TEST_1__');
		peer_completed(self::$connection, self::$settings, $peer);
		peer_completed(self::$connection, self::$settings, $peer);

		$row = mysqli_fetch_assoc(mysqli_query(
			self::$connection,
			'SELECT `downloads` FROM `'.self::$settings['db_prefix'].'torrents` '.
			'WHERE `info_hash` = \'__TEST_1__\';'
		));
		$this->assertIsArray($row);
		$this->assertEquals(2, $row['downloads']);
	}

}
