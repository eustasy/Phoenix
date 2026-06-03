<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../src/functions/auth.login.throttle.delay.php';

class AuthLoginThrottleDelayTest extends TestCase
{
    public function testNoDelayWhenBaseIsZero(): void
    {
        // base 0 disables the throttle regardless of the failure count.
        $this->assertSame(0, auth_login_throttle_delay(5, 0, 8));
    }

    public function testNoDelayWithoutFailures(): void
    {
        $this->assertSame(0, auth_login_throttle_delay(0, 2, 8));
        $this->assertSame(0, auth_login_throttle_delay(-3, 2, 8));
    }

    public function testScalesLinearlyWithFailureCount(): void
    {
        $this->assertSame(2, auth_login_throttle_delay(1, 2, 8));
        $this->assertSame(4, auth_login_throttle_delay(2, 2, 8));
        $this->assertSame(6, auth_login_throttle_delay(3, 2, 8));
    }

    public function testCappedAtMax(): void
    {
        $this->assertSame(8, auth_login_throttle_delay(4, 2, 8));
        $this->assertSame(8, auth_login_throttle_delay(100, 2, 8));
    }
}
