<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class AuthCsrfVerifyTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_POST = [];
        require_once __DIR__.'/../../src/functions/auth.csrf.verify.php';
    }

    public function testTrueWhenTokensMatch()
    {
        $_SESSION['phoenix_csrf'] = 'matching-token';
        $_POST['csrf'] = 'matching-token';

        $this->assertTrue(auth_csrf_verify());
    }

    public function testFalseWhenNoSessionToken()
    {
        $_POST['csrf'] = 'anything';

        $this->assertFalse(auth_csrf_verify());
    }

    public function testFalseWhenNoPostToken()
    {
        $_SESSION['phoenix_csrf'] = 'session-token';

        $this->assertFalse(auth_csrf_verify());
    }

    public function testFalseWhenTokensMismatch()
    {
        $_SESSION['phoenix_csrf'] = 'expected';
        $_POST['csrf'] = 'forged';

        $this->assertFalse(auth_csrf_verify());
    }

    public function testFalseWhenBothTokensEmpty()
    {
        // An empty session token must never validate, even against an empty
        // submitted value — otherwise a token-less request would pass.
        $_SESSION['phoenix_csrf'] = '';
        $_POST['csrf'] = '';

        $this->assertFalse(auth_csrf_verify());
    }

    public function testFalseWhenPostTokenIsNotAString()
    {
        // A `csrf[]=x` query would arrive as an array; the is_string guard
        // keeps hash_equals() from being handed a non-string.
        $_SESSION['phoenix_csrf'] = 'session-token';
        $_POST['csrf'] = ['array', 'value'];

        $this->assertFalse(auth_csrf_verify());
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
    }

}
