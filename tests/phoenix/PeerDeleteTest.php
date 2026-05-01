<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class PeerDeleteTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once self::$settings['model'].'peer.delete.php';
		require_once self::$settings['model'].'db.fetch.once.php';
	}

	protected function tearDown(): void {
		mysqli_query(
			self::$connection,
			'DELETE FROM `'.self::$settings['db_prefix'].'peers` WHERE `info_hash` LIKE \'__TEST_%\';'
		);
	}

	public function testDeletesMatchingRow(): void {
		mysqli_query(
			self::$connection,
			'INSERT INTO `'.self::$settings['db_prefix'].'peers` ( `info_hash`, `peer_id`, `compactv4`, `compactv6`, `portv4`, `portv6`, `updated` ) '.
			'VALUES ( \'__TEST_1__\', \'__TEST_1__\', \'\', \'\', \'0\', \'0\', \''.self::$time.'\');'
		);

		$peer = array(
			'info_hash' => '__TEST_1__',
			'peer_id'   => '__TEST_1__',
		);
		$this->assertTrue(peer_delete(self::$connection, self::$settings, $peer));

		$select = 'SELECT * FROM `'.self::$settings['db_prefix'].'peers` '.
			'WHERE `info_hash` = \'__TEST_1__\' AND `peer_id` = \'__TEST_1__\';';
		$this->assertFalse(mysqli_fetch_once(self::$connection, $select));
	}

}
