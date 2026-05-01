<?php

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class AuthVerifyLoginTest extends TestCase {

	protected function setUp(): void {
		$_POST = array();
	}

	public function testReturnsFalseWhenPasswordNotSet() {
		require_once __DIR__.'/../../src/functions/function.auth.verify.login.php';

		$settings = array(
			'admin_password' => password_hash('correct_password', PASSWORD_DEFAULT),
		);

		$result = auth_verify_login($settings);

		$this->assertFalse($result);
	}

	public function testReturnsFalseWhenPasswordIsIncorrect() {
		require_once __DIR__.'/../../src/functions/function.auth.verify.login.php';

		$_POST['password'] = 'wrong_password';
		$settings = array(
			'admin_password' => password_hash('correct_password', PASSWORD_DEFAULT),
		);

		$result = auth_verify_login($settings);

		$this->assertFalse($result);
	}

	public function testReturnsTrueWhenPasswordIsCorrect() {
		require_once __DIR__.'/../../src/functions/function.auth.verify.login.php';

		$_POST['password'] = 'correct_password';
		$settings = array(
			'admin_password' => password_hash('correct_password', PASSWORD_DEFAULT),
		);

		$result = auth_verify_login($settings);

		$this->assertTrue($result);
	}

	public function testHandlesDifferentPasswordHashAlgorithms() {
		require_once __DIR__.'/../../src/functions/function.auth.verify.login.php';

		$_POST['password'] = 'test123';
		$settings = array(
			'admin_password' => password_hash('test123', PASSWORD_BCRYPT),
		);

		$result = auth_verify_login($settings);

		$this->assertTrue($result);
	}

	protected function tearDown(): void {
		$_POST = array();
	}

}
