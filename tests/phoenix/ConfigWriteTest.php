<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ConfigWriteTest extends TestCase
{
    private string $path;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/config.write.php';
    }

    protected function setUp(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'phxcfg_');
        $this->assertNotFalse($tmp);
        $this->path = $tmp;
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    /** @return array<string, mixed> */
    private function readBack(): array
    {
        $settings = [];
        include $this->path;

        return $settings;
    }

    public function testPreservesExistingKeysAndAppliesChange(): void
    {
        file_put_contents(
            $this->path,
            "<?php\n\$settings['db_pass'] = 'secret';\n".
            "\$settings['api_keys'] = ['*' => 'adminkey'];\n".
            "\$settings['open_tracker'] = true;\n",
        );

        $this->assertTrue(\config_write($this->path, ['admin_password' => 'HASHVALUE', 'open_tracker' => false]));

        $r = $this->readBack();
        // Preserved untouched.
        $this->assertSame('secret', $r['db_pass']);
        $this->assertSame(['*' => 'adminkey'], $r['api_keys']);
        // Added / changed.
        $this->assertSame('HASHVALUE', $r['admin_password']);
        $this->assertFalse($r['open_tracker']);
    }

    public function testStripsPersistentPrefixFromExistingHost(): void
    {
        file_put_contents($this->path, "<?php\n\$settings['db_host'] = 'p:localhost';\n");

        $this->assertTrue(\config_write($this->path, []));

        $this->assertSame('localhost', $this->readBack()['db_host']);
    }

    public function testStripsPersistentPrefixFromChangedHost(): void
    {
        file_put_contents($this->path, "<?php\n\$settings['db_name'] = 'phoenix';\n");

        \config_write($this->path, ['db_host' => 'p:dbhost']);

        $this->assertSame('dbhost', $this->readBack()['db_host']);
    }

    public function testGeneratesValidPhpSource(): void
    {
        file_put_contents($this->path, "<?php\n\$settings['db_name'] = 'phoenix';\n");
        \config_write($this->path, ['admin_password' => 'h']);

        $source = (string) file_get_contents($this->path);
        $this->assertStringStartsWith('<?php', $source);
        // Re-including must not fatal and must round-trip the value.
        $this->assertSame('h', $this->readBack()['admin_password']);
    }

    public function testPreservesFilePermissions(): void
    {
        file_put_contents($this->path, "<?php\n\$settings['db_name'] = 'phoenix';\n");
        // tempnam() creates the file 0600; widen it so a preserved (not reset)
        // mode is observable after the atomic rename swaps the inode.
        chmod($this->path, 0o644);

        \config_write($this->path, ['admin_password' => 'h']);

        $this->assertSame(0o644, fileperms($this->path) & 0o777);
    }

    public function testLeavesNoTempArtifacts(): void
    {
        file_put_contents($this->path, "<?php\n\$settings['db_name'] = 'phoenix';\n");

        // The atomic publish writes a sibling .phxcfg_* temp and renames it over
        // the target; on success nothing new is left behind. Snapshot before/
        // after so a stale artifact from another run can't fail this.
        $dir = dirname($this->path);
        $before = glob($dir.'/.phxcfg_*') ?: [];

        \config_write($this->path, ['admin_password' => 'h']);

        $after = glob($dir.'/.phxcfg_*') ?: [];
        $this->assertSame($before, $after);
    }
}
