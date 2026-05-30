<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class DbPersistHostTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once __DIR__.'/../../src/functions/db.persist.host.php';
	}

	public function testReturnsHostUnchangedWhenPersistFalse(): void {
		$this->assertSame('localhost', db_persist_host('localhost', false));
	}

	public function testPrependsPersistPrefixWhenPersistTrue(): void {
		$this->assertSame('p:localhost', db_persist_host('localhost', true));
	}

	public function testWorksWithIpAddressHost(): void {
		$this->assertSame('p:127.0.0.1', db_persist_host('127.0.0.1', true));
	}

	public function testWorksWithSocketPath(): void {
		// mysqli_connect supports unix socket paths via the host argument.
		$this->assertSame('p:/var/run/mysqld/mysqld.sock', db_persist_host('/var/run/mysqld/mysqld.sock', true));
	}

	public function testNonPersistDoesNotStripExistingPrefix(): void {
		// Function does not normalise; an already-prefixed host stays prefixed.
		// Callers must not pass an already-prefixed host with persist=false.
		$this->assertSame('p:localhost', db_persist_host('p:localhost', false));
	}

	public function testIsNotIdempotent(): void {
		// Documents the gotcha: calling twice with persist=true produces a
		// double prefix. once.db.connect runs at most once per request, so this
		// is fine in production, but tests that re-run the once must reset
		// db_host between invocations.
		$this->assertSame('p:p:localhost', db_persist_host(db_persist_host('localhost', true), true));
	}

}
