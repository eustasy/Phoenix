<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class AdminSetupActionTest extends PhoenixTestCase {

	private static string $isolatedPrefix;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once __DIR__.'/../../src/controller/admin.setup.php';
		require_once __DIR__.'/../../src/model/db.drop.php';
		// Run against an isolated prefix so dropping/creating tables does not
		// disturb fixtures the rest of the suite depends on.
		self::$isolatedPrefix = self::$settings['db_prefix'].'setup_';
	}

	protected function tearDown(): void {
		// Always leave a clean slate; admin_setup_action may have created
		// these and a later test invocation must start from a known state.
		$settings              = self::$settings;
		$settings['db_prefix'] = self::$isolatedPrefix;
		db_drop_table(self::$connection, $settings, 'peers');
		db_drop_table(self::$connection, $settings, 'tasks');
		db_drop_table(self::$connection, $settings, 'torrents');
	}

	public function testReturnsFalseWhenResetDisabledAndTablesAlreadyInstalled(): void {
		// The early-exit guard: an admin must explicitly opt into reset before
		// the controller will touch a populated DB.
		$settings              = self::$settings;
		$settings['db_prefix'] = self::$isolatedPrefix;
		$settings['db_reset']  = false;

		$result = admin_setup_action(self::$connection, $settings, self::$time, true);
		$this->assertFalse($result);
	}

	public function testCreatesTablesOnFreshInstall(): void {
		// tables_installed=false skips the drop branch and goes straight to
		// db_create, returning the success message.
		$settings              = self::$settings;
		$settings['db_prefix'] = self::$isolatedPrefix;

		$result = admin_setup_action(self::$connection, $settings, self::$time, false);
		$this->assertSame('Your MySQL Tracker Database has been setup.', $result);

		// Verify a table actually exists.
		$check = mysqli_query(
			self::$connection,
			'SHOW TABLES LIKE \''.self::$isolatedPrefix.'peers\';'
		);
		$this->assertNotFalse($check);
		$this->assertSame(1, mysqli_num_rows($check));
	}

	public function testReturnsFailureMessageWhenDropFails(): void {
		// tables_installed=true sends the controller through db_drop_table.
		// DROP TABLE IF EXISTS succeeds against a missing table, so just
		// re-running drop on an empty DB wouldn't exercise the failure path.
		// Force a real SQL error by injecting a backtick into the prefix so
		// identifier quoting breaks; db_drop_table echoes mysqli_error on
		// failure, so capture output too.
		$brokenSettings              = self::$settings;
		$brokenSettings['db_prefix'] = 'bad`prefix_';
		$brokenSettings['db_reset']  = true;

		mysqli_report(MYSQLI_REPORT_OFF);
		ob_start();
		try {
			$result = admin_setup_action(self::$connection, $brokenSettings, self::$time, true);
			$this->assertSame('Could not setup the MySQL Database.', $result);
		} finally {
			ob_end_clean();
			mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
		}
	}

	public function testReturnsFailureMessageWhenCreateFails(): void {
		// tables_installed=false skips drop and goes straight to db_create;
		// a broken prefix forces the create to fail and routes to the else
		// branch with the failure message.
		$brokenSettings              = self::$settings;
		$brokenSettings['db_prefix'] = 'bad`prefix_';

		mysqli_report(MYSQLI_REPORT_OFF);
		ob_start();
		try {
			$result = admin_setup_action(self::$connection, $brokenSettings, self::$time, false);
			$this->assertSame('Could not setup the MySQL Database.', $result);
		} finally {
			ob_end_clean();
			mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
		}
	}

	public function testDropsAndRecreatesWhenResetAllowed(): void {
		// Pre-create the tables, then run setup with db_reset=true; both the
		// drop-existing branch and the re-create branch must run cleanly.
		$settings              = self::$settings;
		$settings['db_prefix'] = self::$isolatedPrefix;
		$settings['db_reset']  = true;

		// Seed: create then insert a sentinel row that must NOT survive reset.
		require_once __DIR__.'/../../src/model/db.create.php';
		db_create(self::$connection, $settings);
		mysqli_query(
			self::$connection,
			'INSERT INTO `'.self::$isolatedPrefix.'torrents` (`info_hash`) '.
			'VALUES (\'__TEST_SETUP_RESET__\');'
		);

		$result = admin_setup_action(self::$connection, $settings, self::$time, true);
		$this->assertSame('Your MySQL Tracker Database has been setup.', $result);

		// The sentinel row should have been dropped with the table.
		$check = mysqli_query(
			self::$connection,
			'SELECT * FROM `'.self::$isolatedPrefix.'torrents` '.
			'WHERE `info_hash` = \'__TEST_SETUP_RESET__\';'
		);
		$this->assertNotFalse($check);
		$this->assertSame(0, mysqli_num_rows($check));
	}

}
