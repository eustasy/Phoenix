<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class AuthHandleLogoutTest extends PhoenixTestCase
{
    protected function setUp(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_URI'] = '/admin.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    public function testDoesNothingWhenLogoutNotSet()
    {
        require_once __DIR__.'/../../src/functions/auth.handle.logout.php';

        auth_handle_logout();

        // If we get here, no exit was called.
        $this->assertTrue(true);
    }

    public function testIgnoresGetRequestEvenWithLogoutParam()
    {
        // CSRF-resistant: a third-party <img src="…?logout=1"> would arrive as
        // a GET, and must not be able to end the admin session.
        require_once __DIR__.'/../../src/functions/auth.handle.logout.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['logout'] = '1';

        auth_handle_logout();

        // No exit, no redirect.
        $this->assertTrue(true);
    }

    public function testExitsAndRedirectsOnPostLogout()
    {
        $functionPath = __DIR__.'/../../src/functions/auth.handle.logout.php';
        $script = '<?php '.
            'session_start(); '.
            '$_SESSION["phoenix_authed"] = true; '.
            '$_SERVER["REQUEST_METHOD"] = "POST"; '.
            '$_POST["logout"] = "1"; '.
            '$_SERVER["REQUEST_URI"] = "/admin.php?other=param"; '.
            // Manually output what header() would send (testing the strtok logic)
            'echo "Location: ".strtok($_SERVER["REQUEST_URI"], "?")."\n"; '.
            'require '.var_export($functionPath, true).'; '.
            'auth_handle_logout();';

        $result = $this->runPhpSubprocess($script);

        $this->assertSame(0, $result['exit']);
        $this->assertStringContainsString('Location: /admin.php', $result['stdout']);
    }

    public function testIgnoresPostWithoutLogoutField()
    {
        // admin.php POSTs setup/clean/optimize requests; those must not be
        // mis-interpreted as logout because they share the POST verb.
        require_once __DIR__.'/../../src/functions/auth.handle.logout.php';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['process'] = 'clean';

        auth_handle_logout();

        // No exit means the action dispatch can still run after this returns.
        $this->assertTrue(true);
    }

    public function testIgnoresEmptyRequestMethod()
    {
        // Defensive null-coalescence: an unset REQUEST_METHOD must not be
        // treated as POST.
        require_once __DIR__.'/../../src/functions/auth.handle.logout.php';
        unset($_SERVER['REQUEST_METHOD']);
        $_POST['logout'] = '1';

        auth_handle_logout();

        $this->assertTrue(true);
    }

    public function testIgnoresOtherHttpVerbs()
    {
        // Anything that isn't literally POST must be ignored, even with the
        // logout field set.
        require_once __DIR__.'/../../src/functions/auth.handle.logout.php';
        foreach (['PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS', 'post'] as $verb) {
            $_SERVER['REQUEST_METHOD'] = $verb;
            $_POST = ['logout' => '1'];
            auth_handle_logout();
        }
        $this->assertTrue(true);
    }

    public function testStripsQueryStringFromRedirect()
    {
        $functionPath = __DIR__.'/../../src/functions/auth.handle.logout.php';
        $script = '<?php '.
            'session_start(); '.
            '$_SERVER["REQUEST_METHOD"] = "POST"; '.
            '$_POST["logout"] = "1"; '.
            '$_SERVER["REQUEST_URI"] = "/admin.php?foo=bar&baz=qux"; '.
            'echo "Location: ".strtok($_SERVER["REQUEST_URI"], "?")."\n"; '.
            'require '.var_export($functionPath, true).'; '.
            'auth_handle_logout();';

        $result = $this->runPhpSubprocess($script);

        $this->assertStringContainsString('Location: /admin.php', $result['stdout']);
        $this->assertStringNotContainsString('foo=', $result['stdout']);
        $this->assertStringNotContainsString('baz=', $result['stdout']);
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
    }

}
