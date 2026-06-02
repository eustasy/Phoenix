<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class AuthIsAuthenticatedTest extends TestCase
{
    protected function setUp(): void
    {
        // Ensure clean session state
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    public function testReturnsFalseWhenNotAuthenticated()
    {
        require_once __DIR__.'/../../src/functions/auth.is.authenticated.php';

        $result = auth_is_authenticated();

        $this->assertFalse($result);
    }

    public function testReturnsFalseWhenSessionKeyIsEmpty()
    {
        require_once __DIR__.'/../../src/functions/auth.is.authenticated.php';

        $_SESSION['phoenix_authed'] = false;

        $result = auth_is_authenticated();

        $this->assertFalse($result);
    }

    public function testReturnsTrueWhenAuthenticated()
    {
        require_once __DIR__.'/../../src/functions/auth.is.authenticated.php';

        $_SESSION['phoenix_authed'] = true;

        $result = auth_is_authenticated();

        $this->assertTrue($result);
    }

    public function testReturnsTrueWhenSessionKeyIsTruthy()
    {
        require_once __DIR__.'/../../src/functions/auth.is.authenticated.php';

        $_SESSION['phoenix_authed'] = 'yes';

        $result = auth_is_authenticated();

        $this->assertTrue($result);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

}
