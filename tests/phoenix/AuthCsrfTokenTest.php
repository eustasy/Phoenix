<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class AuthCsrfTokenTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        require_once __DIR__.'/../../src/functions/auth.csrf.token.php';
    }

    public function testMintsHexTokenWhenNoneExists()
    {
        $token = auth_csrf_token();

        // bin2hex(random_bytes(32)) → 64 lowercase hex characters.
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertSame($token, $_SESSION['phoenix_csrf']);
    }

    public function testReturnsSameTokenWithinSession()
    {
        $first = auth_csrf_token();
        $second = auth_csrf_token();

        $this->assertSame($first, $second);
    }

    public function testPreservesAnExistingValidToken()
    {
        $_SESSION['phoenix_csrf'] = 'preexisting-token';

        $this->assertSame('preexisting-token', auth_csrf_token());
    }

    public function testRegeneratesWhenStoredTokenIsEmptyOrNonString()
    {
        $_SESSION['phoenix_csrf'] = '';
        $minted = auth_csrf_token();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $minted);

        $_SESSION['phoenix_csrf'] = ['not', 'a', 'string'];
        $reminted = auth_csrf_token();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $reminted);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

}
