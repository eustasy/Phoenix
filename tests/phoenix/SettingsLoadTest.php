<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class SettingsLoadTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/settings.load.php';
    }

    /** @var list<string> */
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $f) {
            if (is_file($f)) {
                unlink($f);
            }
        }
        $this->tmpFiles = [];
        parent::tearDown();
    }

    /**
     * Write a synthetic settings file containing $settings[...] = ... lines.
     *
     * @param array<string, mixed> $values
     */
    private function makeConfigFile(array $values): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'phx_settings_');
        $this->tmpFiles[] = $tmp;
        $php = "<?php\n";
        foreach ($values as $k => $v) {
            $php .= '$settings['.var_export($k, true).'] = '.var_export($v, true).";\n";
        }
        file_put_contents($tmp, $php);

        return $tmp;
    }

    public function testReturnsDefaultsUntouchedWhenCustomMissing(): void
    {
        // A custom path that doesn't exist takes the fallback branch, which now
        // returns the defaults as-is — no invented credentials. The default file
        // ships empty DB credentials (db_is_configured() reads those as "not
        // configured").
        $default = $this->makeConfigFile([
            'announce_interval' => 1800,
            'db_host' => '',
            'db_user' => '',
            'db_name' => '',
        ]);
        $custom = sys_get_temp_dir().'/phoenix_custom_does_not_exist_'.bin2hex(random_bytes(4)).'.php';

        $result = \settings_load($default, $custom);

        // Default values preserved verbatim.
        $this->assertSame(1800, $result['announce_interval']);
        $this->assertSame('', $result['db_host']);
        $this->assertSame('', $result['db_user']);
        $this->assertSame('', $result['db_name']);
        // No demo credentials are injected (the old fallback set these).
        $this->assertArrayNotHasKey('db_pass', $result);
        $this->assertArrayNotHasKey('db_persist', $result);
        $this->assertArrayNotHasKey('open_tracker', $result);
    }

    public function testCustomOverridesDefault(): void
    {
        // Custom file present. Its values should override the default's
        // for any shared key, while non-shared keys from the default
        // survive and the missing-file fallbacks do NOT fire.
        $default = $this->makeConfigFile([
            'db_host' => '', // the real default ships these empty
            'db_name' => '',
            'announce_interval' => 1800,
        ]);
        $custom = $this->makeConfigFile([
            'db_host' => 'real.example.com',
            'db_name' => 'phoenix_prod',
        ]);

        $result = \settings_load($default, $custom);

        $this->assertSame('real.example.com', $result['db_host']);
        $this->assertSame('phoenix_prod', $result['db_name']);
        // Non-overridden default key survived.
        $this->assertSame(1800, $result['announce_interval']);
        // Missing-file fallbacks should not have been applied.
        $this->assertArrayNotHasKey('db_user', $result);
    }

}
