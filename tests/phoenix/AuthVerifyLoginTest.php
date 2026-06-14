<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class AuthVerifyLoginTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $postBackup;

    protected function setUp(): void
    {
        require_once __DIR__.'/../../src/functions/auth.verify.login.php';
        $this->postBackup = $_POST;
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_POST = $this->postBackup;
    }

    public function testReturnsFalseWhenPasswordNotSet()
    {
        $settings = [
            'admin_password' => password_hash('correct_password', PASSWORD_DEFAULT),
            'admin_totp_secret' => '',
        ];

        $result = auth_verify_login($settings);

        $this->assertFalse($result);
    }

    public function testReturnsFalseWhenPasswordIsIncorrect()
    {
        $_POST['password'] = 'wrong_password';
        $settings = [
            'admin_password' => password_hash('correct_password', PASSWORD_DEFAULT),
            'admin_totp_secret' => '',
        ];

        $result = auth_verify_login($settings);

        $this->assertFalse($result);
    }

    public function testReturnsTrueWhenPasswordIsCorrect()
    {
        $_POST['password'] = 'correct_password';
        $settings = [
            'admin_password' => password_hash('correct_password', PASSWORD_DEFAULT),
            'admin_totp_secret' => '',
        ];

        $result = auth_verify_login($settings);

        $this->assertTrue($result);
    }

    public function testHandlesDifferentPasswordHashAlgorithms()
    {
        $_POST['password'] = 'test123';
        $settings = [
            'admin_password' => password_hash('test123', PASSWORD_BCRYPT),
            'admin_totp_secret' => '',
        ];

        $result = auth_verify_login($settings);

        $this->assertTrue($result);
    }

    ////	TOTP second factor

    public function testReturnsTrueWithCorrectPasswordAndCorrectCode()
    {
        $secret = \eustasy\Authenticatron::makeSecret();
        $_POST['password'] = 'correct_password';
        $_POST['code'] = \eustasy\Authenticatron::getCode($secret);
        $settings = [
            'admin_password' => password_hash('correct_password', PASSWORD_DEFAULT),
            'admin_totp_secret' => $secret,
        ];

        $this->assertTrue(auth_verify_login($settings));
    }

    public function testReturnsFalseWithCorrectPasswordAndWrongCode()
    {
        $secret = \eustasy\Authenticatron::makeSecret();
        $_POST['password'] = 'correct_password';
        $_POST['code'] = '000000';
        $settings = [
            'admin_password' => password_hash('correct_password', PASSWORD_DEFAULT),
            'admin_totp_secret' => $secret,
        ];

        // 000000 is overwhelmingly unlikely to be the live code; guard anyway.
        if (\eustasy\Authenticatron::checkCode('000000', $secret)) {
            $this->markTestSkipped('Live code happened to be 000000.');
        }

        $this->assertFalse(auth_verify_login($settings));
    }

    public function testReturnsFalseWithWrongPasswordEvenWithCorrectCode()
    {
        // A valid second factor must NOT compensate for a wrong password.
        $secret = \eustasy\Authenticatron::makeSecret();
        $_POST['password'] = 'wrong_password';
        $_POST['code'] = \eustasy\Authenticatron::getCode($secret);
        $settings = [
            'admin_password' => password_hash('correct_password', PASSWORD_DEFAULT),
            'admin_totp_secret' => $secret,
        ];

        $this->assertFalse(auth_verify_login($settings));
    }

    public function testReturnsFalseWhenSecretSetButCodeMissing()
    {
        $secret = \eustasy\Authenticatron::makeSecret();
        $_POST['password'] = 'correct_password';
        // No 'code' in POST.
        $settings = [
            'admin_password' => password_hash('correct_password', PASSWORD_DEFAULT),
            'admin_totp_secret' => $secret,
        ];

        $this->assertFalse(auth_verify_login($settings));
    }

}
