<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ViewAdminSupportHtmlTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/html.admin.support.php';
    }

    /** @return array<string, mixed> */
    private function settings(array $overrides = []): array
    {
        return array_merge([
            'phoenix_version' => 'Phoenix Test v.0',
            'admin_password' => '',
        ], $overrides);
    }

    public function testRendersBaseDocument(): void
    {
        $html = view_admin_support_html($this->settings(), true, false);
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<title>Phoenix Admin: Server Support</title>', $html);
        // The Server Support nav link is marked current on this page.
        $this->assertStringContainsString('<a href="?page=support" class="is-active" aria-current="page">', $html);
    }

    public function testReportsCurrentPhpVersion(): void
    {
        $html = view_admin_support_html($this->settings(), true, false);
        $this->assertStringContainsString('PHP Version: '.PHP_VERSION, $html);
        // Composer enforces ^8.2; anything reaching this view is supported.
        $this->assertStringContainsString('Your PHP version is supported.', $html);
    }

    public function testFlagsUnsupportedPhpVersion(): void
    {
        // Manual installs may bypass composer's php: ^8.2 constraint, so the
        // view must still warn when the runtime is too old. The override
        // parameter exists purely so this branch can be reached from tests.
        $html = view_admin_support_html($this->settings(), true, false, '', '8.1.99');
        $this->assertStringContainsString('Phoenix requires PHP &gt;= 8.2.', $html);
        $this->assertStringContainsString('PHP Version: 8.1.99', $html);
        $this->assertStringNotContainsString('Your PHP version is supported.', $html);
    }

    public function testReportsMysqlSupportWhenAvailable(): void
    {
        // Default has_mysqli (class_exists) — mysqli is present in the test env.
        $html = view_admin_support_html($this->settings(), true, false);
        $this->assertStringContainsString('Your server supports MySQL.', $html);
    }

    public function testFlagsMissingMysqliExtension(): void
    {
        // Manual installs may bypass composer's ext-mysqli requirement, so the
        // view must still warn when mysqli is not loaded. The override exists
        // purely so this branch can be reached from tests.
        $html = view_admin_support_html($this->settings(), true, false, '', null, false);
        $this->assertStringContainsString('Your server does not support MySQL.', $html);
        $this->assertStringNotContainsString('Your server supports MySQL.', $html);
    }

    public function testShowsTablesInstalledWithSize(): void
    {
        $html = view_admin_support_html(
            $this->settings(),
            true,
            ['Data' => 100, 'Indexes' => 50, 'Total' => 1234567, 'Free' => 0],
        );
        $this->assertStringContainsString('All your tables are installed.', $html);
        // Rendered as a size, not a raw byte count.
        $this->assertStringContainsString('1.2 MB', $html);
    }

    public function testShowsTablesMissingWarningWhenNotInstalled(): void
    {
        $html = view_admin_support_html($this->settings(), false, false);
        $this->assertStringContainsString('Some or all of your tables are not installed.', $html);
        // Points the operator at Utilities to install them.
        $this->assertStringContainsString('?page=utilities', $html);
    }

    /**
     * @param array<string, array<string, bool|string>> $overrides
     * @return array<string, array<string, bool|string>>
     */
    private function extras(array $overrides = []): array
    {
        return array_replace_recursive([
            'geo' => ['enabled' => true, 'reader' => 'extension', 'database' => '/var/lib/GeoIP/GeoLite2-Country.mmdb', 'readable' => true],
            'sentry' => ['installed' => true, 'dsn' => true, 'reporting' => true],
            'totp' => ['installed' => true, 'enabled' => true, 'password' => true],
            'backups' => ['requested' => true, 'available' => true],
        ], $overrides);
    }

    /** @param array<string, array<string, bool|string>> $extras */
    private function withExtras(array $extras): string
    {
        return view_admin_support_html($this->settings(), true, false, 'tok', null, null, $extras);
    }

    public function testExtrasSectionIsOmittedWhenNotSupplied(): void
    {
        // The page predates the extras and must still render without them.
        $html = view_admin_support_html($this->settings(), true, false, 'tok');

        $this->assertStringNotContainsString('Optional extras', $html);
    }

    public function testReportsWhichGeoReaderIsInUseAndWhereTheDatabaseIs(): void
    {
        // Which reader is loaded is the difference between microseconds and
        // half a millisecond per announce, so the page names it.
        $html = $this->withExtras($this->extras());

        $this->assertStringContainsString('Optional extras', $html);
        $this->assertStringContainsString('C extension', $html);
        $this->assertStringContainsString('/var/lib/GeoIP/GeoLite2-Country.mmdb', $html);
    }

    public function testWarnsWhenGeoFallsBackToThePurePhpReader(): void
    {
        $html = $this->withExtras($this->extras(['geo' => ['reader' => 'php']]));

        $this->assertStringContainsString('alert-warning', $html);
        $this->assertStringContainsString('pure-PHP reader', $html);
        $this->assertStringContainsString('maxminddb', $html);
        // Still says where the database is, since it is still being read.
        $this->assertStringContainsString('/var/lib/GeoIP/GeoLite2-Country.mmdb', $html);
    }

    public function testGeoOnWithNoDatabaseIsAFault(): void
    {
        // A setting asking for something the server cannot deliver.
        $html = $this->withExtras($this->extras(['geo' => ['readable' => false]]));

        $this->assertStringContainsString('alert-danger', $html);
        $this->assertStringContainsString('no database was found', $html);
    }

    public function testGeoOffIsStatedNotFlagged(): void
    {
        $html = $this->withExtras($this->extras(['geo' => ['enabled' => false]]));

        $this->assertStringContainsString('Geo enrichment is off', $html);
        $this->assertStringNotContainsString('alert-warning', $html);
        $this->assertStringNotContainsString('alert-danger', $html);
    }

    public function testWarnsWhenTwoFactorIsOff(): void
    {
        $html = $this->withExtras($this->extras(['totp' => ['enabled' => false]]));

        $this->assertStringContainsString('alert-warning', $html);
        $this->assertStringContainsString('Two-factor authentication is off', $html);
    }

    public function testNoPasswordOutranksTheTwoFactorNotice(): void
    {
        // A panel with no password has no login to add a factor to; saying
        // "enable 2FA" there would be beside the point.
        $html = $this->withExtras($this->extras(['totp' => ['password' => false, 'enabled' => false]]));

        $this->assertStringContainsString('no password', $html);
        $this->assertStringNotContainsString('Two-factor authentication is off', $html);
    }

    public function testCompressionRequestedWithoutZlibIsAFault(): void
    {
        $html = $this->withExtras($this->extras(['backups' => ['available' => false]]));

        $this->assertStringContainsString('alert-danger', $html);
        $this->assertStringContainsString('no zlib support', $html);
    }

    public function testCompressionOffIsStatedNotFlagged(): void
    {
        $html = $this->withExtras($this->extras(['backups' => ['requested' => false]]));

        $this->assertStringContainsString('plain SQL', $html);
        $this->assertStringNotContainsString('alert-danger', $html);
    }

    public function testSentryInstalledButNotReporting(): void
    {
        $html = $this->withExtras($this->extras(['sentry' => ['reporting' => false]]));

        $this->assertStringContainsString('not reporting', $html);
        $this->assertStringContainsString('report_errors', $html);
    }
}
