<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use mysqli;
use PHPUnit\Framework\TestCase;

abstract class PhoenixTestCase extends TestCase {

	protected static mysqli $connection;

	/** @var array<string, mixed> */
	protected static array $settings;

	protected static int $time;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		self::$connection = $GLOBALS['phoenix_connection'];
		self::$settings   = $GLOBALS['phoenix_settings'];
		self::$time       = $GLOBALS['phoenix_time'];
	}

}
