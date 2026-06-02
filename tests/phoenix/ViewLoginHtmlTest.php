<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ViewLoginHtmlTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/html.login.php';
    }

    public function testRendersFormWithoutErrorByDefault(): void
    {
        $html = view_login_html();
        $this->assertStringContainsString('<form method="POST"', $html);
        $this->assertStringContainsString('name="process" value="login"', $html);
        $this->assertStringContainsString('type="password"', $html);
        $this->assertStringNotContainsString('Incorrect password.', $html);
    }

    public function testShowsErrorBannerWhenFlagged(): void
    {
        // The controller passes true here after a failed POST so the next
        // render tells the user their password was wrong.
        $html = view_login_html(true);
        $this->assertStringContainsString('Incorrect password.', $html);
        $this->assertStringContainsString('background-pomegranate', $html);
    }

    public function testEmitsValidHtmlDocument(): void
    {
        $html = view_login_html();
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<html lang="en">', $html);
        $this->assertStringContainsString('</html>', $html);
        $this->assertStringContainsString('<title>Phoenix &mdash; Login</title>', $html);
    }

    public function testReturnsString(): void
    {
        // view_* functions never echo or exit; the caller does. Pin that.
        $this->assertIsString(view_login_html());
        $this->assertIsString(view_login_html(true));
    }

}
