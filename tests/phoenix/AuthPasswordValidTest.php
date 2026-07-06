<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class AuthPasswordValidTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__.'/../../src/functions/auth.password.valid.php';
    }

    public function testAcceptsATwelveCharacterPassword(): void
    {
        $this->assertNull(auth_password_valid('correct-horse'));   // 13 chars
        $this->assertNull(auth_password_valid('abcdefghijkl'));     // exactly 12
    }

    public function testRejectsShorterThanTwelve(): void
    {
        $this->assertSame('The password must be at least 12 characters.', auth_password_valid('abcdefghijk')); // 11
        $this->assertSame('The password must be at least 12 characters.', auth_password_valid(''));
    }

    public function testCountsCharactersNotBytesForTheMinimum(): void
    {
        // Twelve multibyte characters is >12 bytes but still 12 characters — OK.
        $this->assertNull(auth_password_valid(str_repeat('é', 12)));
    }

    public function testRejectsMoreThanSeventyTwoBytes(): void
    {
        $this->assertNull(auth_password_valid(str_repeat('a', 72)));           // exactly 72 bytes
        $this->assertSame(
            'The password must be at most 72 bytes (a bcrypt limit).',
            auth_password_valid(str_repeat('a', 73)),
        );
        // 36 two-byte chars = 72 bytes (OK); 37 = 74 bytes (rejected).
        $this->assertNull(auth_password_valid(str_repeat('é', 36)));
        $this->assertSame(
            'The password must be at most 72 bytes (a bcrypt limit).',
            auth_password_valid(str_repeat('é', 37)),
        );
    }
}
