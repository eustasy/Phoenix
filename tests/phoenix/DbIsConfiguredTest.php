<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class DbIsConfiguredTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once __DIR__.'/../../src/functions/db.is.configured.php';
	}

	/** @return array<string, mixed> */
	private function complete(): array {
		return array(
			'db_host' => 'localhost',
			'db_user' => 'phoenix',
			'db_pass' => 'secret',
			'db_name' => 'phoenix_db',
		);
	}

	public function testReturnsTrueWhenAllRequiredSet(): void {
		$this->assertTrue(db_is_configured($this->complete()));
	}

	public function testReturnsFalseWhenHostMissing(): void {
		$s = $this->complete();
		unset($s['db_host']);
		$this->assertFalse(db_is_configured($s));
	}

	public function testReturnsFalseWhenUserMissing(): void {
		$s = $this->complete();
		unset($s['db_user']);
		$this->assertFalse(db_is_configured($s));
	}

	public function testReturnsFalseWhenNameMissing(): void {
		$s = $this->complete();
		unset($s['db_name']);
		$this->assertFalse(db_is_configured($s));
	}

	public function testReturnsFalseWhenHostIsEmptyString(): void {
		$s = $this->complete();
		$s['db_host'] = '';
		$this->assertFalse(db_is_configured($s));
	}

	public function testReturnsFalseWhenUserIsEmptyString(): void {
		$s = $this->complete();
		$s['db_user'] = '';
		$this->assertFalse(db_is_configured($s));
	}

	public function testReturnsFalseWhenNameIsEmptyString(): void {
		$s = $this->complete();
		$s['db_name'] = '';
		$this->assertFalse(db_is_configured($s));
	}

	public function testReturnsFalseWhenAllEmpty(): void {
		$this->assertFalse(db_is_configured(array(
			'db_host' => '',
			'db_user' => '',
			'db_pass' => '',
			'db_name' => '',
		)));
	}

	public function testReturnsFalseForEmptyArray(): void {
		$this->assertFalse(db_is_configured(array()));
	}

	public function testEmptyPasswordIsAllowed(): void {
		// Some MySQL servers permit passwordless local connections, so an empty
		// db_pass must not disqualify a configuration.
		$s = $this->complete();
		$s['db_pass'] = '';
		$this->assertTrue(db_is_configured($s));
	}

	public function testMissingPasswordIsAllowed(): void {
		$s = $this->complete();
		unset($s['db_pass']);
		$this->assertTrue(db_is_configured($s));
	}

}
