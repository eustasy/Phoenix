<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class InstallBuildConfigTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/install.build.config.php';
    }

    /** @return array<string, mixed> */
    private function fixtureValues(array $overrides = []): array
    {
        return array_merge([
            'db_host' => 'localhost',
            'db_user' => 'phoenix',
            'db_pass' => 'p@ss',
            'db_name' => 'phoenix',
            'db_prefix' => 'phx_',
            'db_persist' => true,
            'open_tracker' => false,
            'public_index' => true,
            'admin_password' => 'pw_hash',
        ], $overrides);
    }

    /**
     * Renders the config string and includes it, returning the resulting
     * $settings array. This proves both that the output parses as PHP and
     * that it sets the expected keys.
     *
     * @param  array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function evaluateConfig(array $values): array
    {
        $config = install_build_config($values);
        $tmp = tempnam(sys_get_temp_dir(), 'phx_install_test_');
        file_put_contents($tmp, $config);
        try {
            $settings = [];
            include $tmp;
        } finally {
            unlink($tmp);
        }

        return $settings;
    }

    public function testProducesValidPhpSettingExpectedKeys(): void
    {
        $settings = $this->evaluateConfig($this->fixtureValues());
        $this->assertSame('localhost', $settings['db_host']);
        $this->assertSame('phoenix', $settings['db_user']);
        $this->assertSame('p@ss', $settings['db_pass']);
        $this->assertSame('phoenix', $settings['db_name']);
        $this->assertSame('phx_', $settings['db_prefix']);
        $this->assertSame('pw_hash', $settings['admin_password']);
    }

    public function testBooleanValuesEmittedAsLiterals(): void
    {
        $settings = $this->evaluateConfig($this->fixtureValues());
        $this->assertTrue($settings['db_persist']);
        $this->assertFalse($settings['open_tracker']);
        $this->assertTrue($settings['public_index']);
    }

    public function testDbResetIsAlwaysFalse(): void
    {
        $settings = $this->evaluateConfig($this->fixtureValues());
        $this->assertFalse($settings['db_reset']);
    }

    public function testEscapesSingleQuotesInStringValues(): void
    {
        $settings = $this->evaluateConfig($this->fixtureValues([
            'db_pass' => "it's-a-pass",
        ]));
        $this->assertSame("it's-a-pass", $settings['db_pass']);
    }

    public function testEscapesBackslashesInStringValues(): void
    {
        $settings = $this->evaluateConfig($this->fixtureValues([
            'db_pass' => 'back\\slash',
        ]));
        $this->assertSame('back\\slash', $settings['db_pass']);
    }

    public function testStartsWithPhpOpenTag(): void
    {
        $config = install_build_config($this->fixtureValues());
        $this->assertStringStartsWith('<?php', $config);
    }

    public function testWritesAdminTotpSecretWhenPresent(): void
    {
        $settings = $this->evaluateConfig($this->fixtureValues([
            'admin_totp_secret' => 'JBSWY3DPEHPK3PXP',
        ]));
        $this->assertSame('JBSWY3DPEHPK3PXP', $settings['admin_totp_secret']);
    }

    public function testWritesEmptyAdminTotpSecretWhenAbsent(): void
    {
        // fixtureValues() does not include admin_totp_secret, so the builder's
        // ?? '' fallback must still emit the key (empty) — the default config
        // declares it, and code reads it with no fallback layer.
        $settings = $this->evaluateConfig($this->fixtureValues());
        $this->assertArrayHasKey('admin_totp_secret', $settings);
        $this->assertSame('', $settings['admin_totp_secret']);
    }

}
