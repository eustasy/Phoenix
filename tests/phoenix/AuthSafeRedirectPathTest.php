<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class AuthSafeRedirectPathTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/auth.safe.redirect.path.php';
    }

    public function testKeepsSameOriginPaths(): void
    {
        $this->assertSame('/admin.php', auth_safe_redirect_path('/admin.php'));
        $this->assertSame('/admin.php?page=settings', auth_safe_redirect_path('/admin.php?page=settings'));
        $this->assertSame('/', auth_safe_redirect_path('/'));
        // A subdirectory install is still a single leading slash.
        $this->assertSame('/tracker/admin.php', auth_safe_redirect_path('/tracker/admin.php'));
    }

    public function testFallsBackForOffSiteTargets(): void
    {
        // Protocol-relative and its backslash variant → off-site → fallback.
        // The fallback is query-only, so it resolves against the current script
        // and survives admin.php being renamed.
        $this->assertSame('?page=dashboard', auth_safe_redirect_path('//evil.com'));
        $this->assertSame('?page=dashboard', auth_safe_redirect_path('//evil.com/path'));
        $this->assertSame('?page=dashboard', auth_safe_redirect_path('/\\evil.com'));
        // Absolute URL (absolute-form request target).
        $this->assertSame('?page=dashboard', auth_safe_redirect_path('https://evil.com'));
        // No leading slash at all → not a same-origin absolute path.
        $this->assertSame('?page=dashboard', auth_safe_redirect_path('evil.com'));
        $this->assertSame('?page=dashboard', auth_safe_redirect_path('admin.php?x=1'));
        $this->assertSame('?page=dashboard', auth_safe_redirect_path(''));
    }

    public function testHonoursCustomFallback(): void
    {
        $this->assertSame('login.php', auth_safe_redirect_path('//evil.com', 'login.php'));
    }
}
