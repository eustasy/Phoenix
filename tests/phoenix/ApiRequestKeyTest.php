<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/functions/api.request.key.php';

class ApiRequestKeyTest extends PhoenixTestCase
{
    /** @var array<string, mixed> */
    private array $serverBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serverBackup = $_SERVER;
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        parent::tearDown();
    }

    public function testStripsBearerPrefix(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer abc123';
        $this->assertSame('abc123', \api_request_key());
    }

    public function testBearerPrefixIsCaseInsensitive(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'bearer abc123';
        $this->assertSame('abc123', \api_request_key());
    }

    public function testAcceptsBareKeyWithoutScheme(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'rawkey';
        $this->assertSame('rawkey', \api_request_key());
    }

    public function testTrimsSurroundingWhitespace(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = '  Bearer   spaced  ';
        $this->assertSame('spaced', \api_request_key());
    }

    public function testFallsBackToRedirectVariable(): void
    {
        // Some Apache setups surface only REDIRECT_HTTP_AUTHORIZATION.
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer fromredirect';
        $this->assertSame('fromredirect', \api_request_key());
    }

    public function testReturnsEmptyWhenAbsent(): void
    {
        $this->assertSame('', \api_request_key());
    }
}
