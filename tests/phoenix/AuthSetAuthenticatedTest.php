<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class AuthSetAuthenticatedTest extends TestCase {

	protected function setUp(): void {
		$_SESSION = array();
	}

	public function testSetsSessionVariable() {
		require_once __DIR__.'/../../src/functions/function.auth.set.authenticated.php';

		$this->assertArrayNotHasKey('phoenix_authed', $_SESSION);

		auth_set_authenticated();

		$this->assertArrayHasKey('phoenix_authed', $_SESSION);
		$this->assertTrue($_SESSION['phoenix_authed']);
	}

	public function testOverwritesExistingSessionVariable() {
		require_once __DIR__.'/../../src/functions/function.auth.set.authenticated.php';

		$_SESSION['phoenix_authed'] = false;

		auth_set_authenticated();

		$this->assertTrue($_SESSION['phoenix_authed']);
	}

	protected function tearDown(): void {
		$_SESSION = array();
	}

}
