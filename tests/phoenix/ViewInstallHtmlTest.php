<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ViewInstallHtmlTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/html.install.php';
    }

    /** @return array<string, mixed> */
    private function form(): array
    {
        return [
            'db_host' => 'localhost',
            'db_user' => 'root',
            'db_name' => 'phoenix',
            'db_prefix' => 'phoenix_',
            'db_persist' => true,
            'open_tracker' => true,
            'public_index' => false,
        ];
    }

    public function testRendersFormWhenConfigDirIsWritable(): void
    {
        $html = view_install_html(true, null, $this->form());
        $this->assertStringContainsString('<form method="POST"', $html);
        $this->assertStringContainsString('name="process" value="install"', $html);
        $this->assertStringContainsString('value="localhost"', $html);
        $this->assertStringContainsString('value="phoenix_"', $html);
        $this->assertStringNotContainsString('config/</code> is not writable', $html);
    }

    public function testShowsWritabilityWarningWhenConfigDirIsLocked(): void
    {
        // settings_writable=false swaps the form for an instructional banner;
        // the form must NOT render so the user can't submit credentials that
        // can't be persisted.
        $html = view_install_html(false, null, $this->form());
        $this->assertStringContainsString('config/</code> is not writable', $html);
        $this->assertStringNotContainsString('<form method="POST"', $html);
    }

    public function testShowsErrorBannerWhenInstallFailed(): void
    {
        // install_error is set when a previous POST hit the "could not connect"
        // or "could not write config" branch in admin_install_controller.
        $html = view_install_html(true, 'Could not connect to the database', $this->form());
        $this->assertStringContainsString('Could not connect to the database', $html);
        $this->assertStringContainsString('background-pomegranate', $html);
    }

    public function testEscapesErrorAndFormValues(): void
    {
        // Both branches html-encode their inputs; pin that against future
        // refactors that might forget the htmlspecialchars wrap.
        $evil = $this->form();
        $evil['db_host'] = '"><script>alert(1)</script>';
        $evil['db_user'] = '<b>raw</b>';
        $html = view_install_html(true, '<bad>error</bad>', $evil);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<b>raw</b>', $html);
        $this->assertStringNotContainsString('<bad>error</bad>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testCheckboxesReflectFormState(): void
    {
        $form = $this->form();
        $form['db_persist'] = false;
        $form['open_tracker'] = false;
        $form['public_index'] = true;
        $html = view_install_html(true, null, $form);

        // Only public_index should be checked.
        $this->assertStringContainsString('name="public_index" value="1" checked', $html);
        $this->assertStringContainsString('name="db_persist" value="1">', $html);
        $this->assertStringContainsString('name="open_tracker" value="1">', $html);
    }

    public function testEmitsValidHtmlDocument(): void
    {
        $html = view_install_html(true, null, $this->form());
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<title>Phoenix Setup</title>', $html);
        $this->assertStringContainsString('</html>', $html);
    }

    public function testOmitsTwoFactorSectionWhenNoSecretPassed(): void
    {
        // No secret => verification library absent => password-only install,
        // unchanged. The whole section must be gone.
        $html = view_install_html(true, null, $this->form());
        $this->assertStringNotContainsString('name="totp_secret"', $html);
        $this->assertStringNotContainsString('name="totp_code"', $html);
        $this->assertStringNotContainsString('Two-Factor Authentication', $html);
    }

    public function testRendersTwoFactorSectionWithQrWhenSecretPassed(): void
    {
        // generateQrCode() returns a complete data URI, so the view uses it as
        // the <img src> verbatim.
        $qr = 'data:image/png;base64,QUJDREVG';
        $html = view_install_html(true, null, $this->form(), 'JBSWY3DPEHPK3PXP', $qr, 'otpauth://totp/x');
        $this->assertStringContainsString('Two-Factor Authentication', $html);
        // Hidden field round-trips the secret.
        $this->assertStringContainsString('name="totp_secret" value="JBSWY3DPEHPK3PXP"', $html);
        $this->assertStringContainsString('name="totp_code"', $html);
        // QR data URI used as-is for the src, exactly once (no doubled prefix).
        $this->assertStringContainsString('<img src="'.$qr.'"', $html);
        $this->assertStringNotContainsString('data:image/png;base64,data:image/png;base64', $html);
    }

    public function testRendersManualEntryWhenNoQrAvailable(): void
    {
        // GD unavailable: controller passes a secret + url but null QR. We must
        // fall back to showing the secret and otpauth URL for manual entry.
        $html = view_install_html(true, null, $this->form(), 'JBSWY3DPEHPK3PXP', null, 'otpauth://totp/x?secret=JBSWY3DPEHPK3PXP');
        $this->assertStringContainsString('name="totp_secret" value="JBSWY3DPEHPK3PXP"', $html);
        $this->assertStringContainsString('name="totp_code"', $html);
        $this->assertStringContainsString('JBSWY3DPEHPK3PXP', $html);
        $this->assertStringContainsString('otpauth://totp/x', $html);
        $this->assertStringNotContainsString('data:image/png;base64', $html);
    }

}
