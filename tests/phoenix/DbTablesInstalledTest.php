<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class DbTablesInstalledTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once __DIR__.'/../../src/model/db.tables.installed.php';
	}

	public function testReturnsTrueWhenAllDefaultTablesExist(): void {
		// Bootstrap already ran db_create() against the TESTING_-prefixed
		// tables, so the default check should pass.
		$this->assertTrue(db_tables_installed(self::$connection, self::$settings));
	}

	public function testReturnsTrueForExplicitSubset(): void {
		// Pinning the explicit-list overload — passing fewer tables still
		// returns true if all of them exist.
		$this->assertTrue(
			db_tables_installed(self::$connection, self::$settings, ['peers'])
		);
	}

	public function testReturnsFalseWhenOneTableMissing(): void {
		// A table name that doesn't exist under any prefix → at least one
		// of the requested tables is absent → false.
		$this->assertFalse(
			db_tables_installed(
				self::$connection,
				self::$settings,
				['peers', 'no_such_table_xyzzy']
			)
		);
	}

	public function testReturnsFalseUnderUnknownPrefix(): void {
		// Different prefix → none of the prefixed names match → false.
		$settings              = self::$settings;
		$settings['db_prefix'] = 'phoenix_no_such_prefix_';
		$this->assertFalse(db_tables_installed(self::$connection, $settings));
	}

	public function testReturnsTrueForEmptyList(): void {
		// Vacuously true: zero tables are required, so all of them exist.
		$this->assertTrue(
			db_tables_installed(self::$connection, self::$settings, [])
		);
	}

}
