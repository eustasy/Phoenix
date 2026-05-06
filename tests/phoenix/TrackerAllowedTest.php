<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class TrackerAllowedTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once __DIR__.'/../../src/model/torrents.select.allowed.php';
	}

	protected function tearDown(): void {
		mysqli_query(
			self::$connection,
			'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` LIKE \'__TEST_%\';'
		);
	}

	public function testReturnsEmptyArrayWhenNoTorrents(): void {
		$this->assertSame(array(), torrents_select_allowed(self::$connection, self::$settings));
	}

	public function testReturnsListOfInfoHashes(): void {
		mysqli_query(
			self::$connection,
			'INSERT INTO `'.self::$settings['db_prefix'].'torrents` ( `info_hash` ) '.
			'VALUES (\'__TEST_1__\'), (\'__TEST_2__\'), (\'__TEST_3__\');'
		);
		$result = torrents_select_allowed(self::$connection, self::$settings);
		$this->assertCount(3, $result);
		$this->assertContains('__TEST_1__', $result);
		$this->assertContains('__TEST_2__', $result);
		$this->assertContains('__TEST_3__', $result);
	}

}
