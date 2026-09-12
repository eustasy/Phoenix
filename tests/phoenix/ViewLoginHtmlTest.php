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

    public function testCarriesAUsernameFieldForPasswordManagers(): void
    {
        // Without one, Chrome treats the authentication-code box as the username
        // — autofilling into it and offering to save the code as the username.
        // It must come before the password field and be a real input, not
        // display:none, which the heuristics skip.
        $html = view_login_html(false, true);

        $this->assertStringContainsString('autocomplete="username"', $html);
        $this->assertLessThan(
            strpos($html, 'type="password"'),
            strpos($html, 'autocomplete="username"'),
            'the username field must precede the password field',
        );
        // Visually hidden, not display:none / [hidden] — Chrome skips those.
        $this->assertStringContainsString('class="ph-visually-hidden"', $html);
        preg_match('/<input[^>]*autocomplete="username"[^>]*>/', $html, $m);
        $this->assertNotEmpty($m, 'username input should render');
        // The standalone hidden attribute, not the aria-hidden it does carry.
        $this->assertDoesNotMatchRegularExpression('/\shidden[\s>]/', $m[0]);
    }

    public function testShowsErrorBannerWhenFlagged(): void
    {
        // The controller passes true here after a failed POST so the next
        // render tells the user their password was wrong.
        $html = view_login_html(true);
        $this->assertStringContainsString('Incorrect password.', $html);
        $this->assertStringContainsString('alert-danger', $html);
    }

    public function testEmitsValidHtmlDocument(): void
    {
        $html = view_login_html();
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<html lang="en"', $html);
        $this->assertStringContainsString('</html>', $html);
        $this->assertStringContainsString('<title>Phoenix — Log in</title>', $html);
    }

    public function testVersionFlowsIntoFooter(): void
    {
        $html = view_login_html(false, false, 'v5.0 Boulevard');
        $this->assertStringContainsString('v5.0 Boulevard', $html);
    }

    public function testReturnsString(): void
    {
        // view_* functions never echo or exit; the caller does. Pin that.
        $this->assertIsString(view_login_html());
        $this->assertIsString(view_login_html(true));
    }

    public function testOmitsCodeFieldByDefault(): void
    {
        // Password-only install: no second factor enrolled, so no code box.
        $html = view_login_html();
        $this->assertStringNotContainsString('name="code"', $html);
    }

    public function testRendersCodeFieldWhenTotpRequired(): void
    {
        $html = view_login_html(false, true);
        $this->assertStringContainsString('name="code"', $html);
        $this->assertStringContainsString('autocomplete="one-time-code"', $html);
    }

}
