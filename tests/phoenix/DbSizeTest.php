<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class DbSizeTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once __DIR__.'/../../src/model/db.size.php';
	}

	public function testReturnsExpectedKeys(): void {
		$row = db_size(self::$connection, self::$settings);

		$this->assertIsArray($row);
		foreach (['Data', 'Indexes', 'Total', 'Free'] as $key) {
			$this->assertArrayHasKey($key, $row);
		}
	}

	public function testTotalIsNonNegative(): void {
		// Bootstrap-created tables make the schema non-empty, so Total
		// should be a parseable non-negative integer string.
		$row = db_size(self::$connection, self::$settings);

		$this->assertIsArray($row);
		$this->assertGreaterThanOrEqual(0, intval($row['Total']));
	}

	public function testReturnsFalseForUnknownSchema(): void {
		// information_schema.TABLES with a non-existent table_schema name
		// yields zero rows, which the function maps to false.
		$settings            = self::$settings;
		$settings['db_name'] = 'phoenix_no_such_schema_xyzzy';
		$this->assertFalse(db_size(self::$connection, $settings));
	}

}
