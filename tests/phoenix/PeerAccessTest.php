<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class PeerAccessTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once self::$settings['functions'].'function.peer.access.php';
		require_once self::$settings['functions'].'function.mysqli.fetch.once.php';
	}

	protected function tearDown(): void {
		mysqli_query(
			self::$connection,
			'DELETE FROM `'.self::$settings['db_prefix'].'peers` WHERE `info_hash` LIKE \'__TEST_%\';'
		);
	}

	public function testUpdatesLeftValue(): void {
		mysqli_query(
			self::$connection,
			'INSERT INTO `'.self::$settings['db_prefix'].'peers` ( `info_hash`, `peer_id`, `compactv4`, `compactv6`, `portv4`, `portv6`, `left`, `updated` ) '.
			'VALUES (\'__TEST_1__\', \'__TEST_1__\', \'\', \'\', \'0\', \'0\', \'3\', \''.self::$time.'\');'
		);

		$peer = array(
			'info_hash'  => '__TEST_1__',
			'peer_id'    => '__TEST_1__',
			'uploaded'   => 0,
			'downloaded' => 0,
			'left'       => 2,
		);
		$this->assertTrue(peer_access(self::$connection, self::$settings, self::$time, $peer));

		$select = 'SELECT `left` FROM `'.self::$settings['db_prefix'].'peers` '.
			'WHERE `info_hash` = \'__TEST_1__\' AND `peer_id` = \'__TEST_1__\';';
		$result = mysqli_fetch_once(self::$connection, $select);
		$this->assertIsArray($result);
		$this->assertEquals(2, $result['left']);
	}

}
