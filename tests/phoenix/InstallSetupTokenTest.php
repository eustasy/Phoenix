<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../src/functions/install.setup.token.php';

class InstallSetupTokenTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/phxtok_'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function testCreatesAndPersistsAToken(): void
    {
        $token = install_setup_token($this->path);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{36}$/', $token);
        $this->assertFileExists($this->path);
        $this->assertSame($token, trim((string) file_get_contents($this->path)));
    }

    public function testReusesAnExistingToken(): void
    {
        $first = install_setup_token($this->path);
        $second = install_setup_token($this->path);
        $this->assertSame($first, $second);
    }

    public function testRegeneratesWhenTheFileIsEmpty(): void
    {
        file_put_contents($this->path, '');
        $token = install_setup_token($this->path);
        $this->assertNotSame('', $token);
        $this->assertSame($token, trim((string) file_get_contents($this->path)));
    }
}
