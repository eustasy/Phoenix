<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class AdminBackupDeleteActionTest extends PhoenixTestCase
{
    private string $dir = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/controller/admin.backup.delete.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/phx_bakdel_'.bin2hex(random_bytes(4)).'/';
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'*') ?: [] as $path) {
            if (is_dir($path)) {
                foreach (glob($path.'/*') ?: [] as $inner) {
                    @unlink($inner);
                }
                @rmdir($path);
                continue;
            }
            @unlink($path);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function settingsWithDir(): array
    {
        $settings = self::$settings;
        $settings['backup_dir'] = $this->dir;

        return $settings;
    }

    private function makeBackup(string $suffix = '20240101_000000'): string
    {
        $name = self::$settings['db_name'].'.'.$suffix;
        mkdir($this->dir.$name);
        file_put_contents($this->dir.$name.'/schema.sql', '-- sql');
        file_put_contents($this->dir.$name.'/torrents.sql', '-- sql');

        return $name;
    }

    public function testDeletesADirectoryBackup(): void
    {
        $name = $this->makeBackup();

        $result = \admin_backup_delete_action($this->settingsWithDir(), $name);

        $this->assertSame('Deleted '.$name.'.', $result);
        $this->assertDirectoryDoesNotExist($this->dir.$name);
    }

    public function testDeletesALegacySingleFileBackup(): void
    {
        $name = self::$settings['db_name'].'.20240101_0000.sql';
        file_put_contents($this->dir.$name, '-- sql');

        $result = \admin_backup_delete_action($this->settingsWithDir(), $name);

        $this->assertSame('Deleted '.$name.'.', $result);
        $this->assertFileDoesNotExist($this->dir.$name);
    }

    public function testUnknownNameIsNotFound(): void
    {
        $this->assertSame('Backup not found.', \admin_backup_delete_action($this->settingsWithDir(), 'nope'));
    }

    public function testEmptyNameIsNotFound(): void
    {
        $this->assertSame('Backup not found.', \admin_backup_delete_action($this->settingsWithDir(), ''));
    }

    /**
     * A posted name is never joined onto a path directly — anything carrying a
     * directory component is refused before the list is consulted, and an
     * unlisted-but-real path is refused by the list check.
     *
     * @return array<string, array{string}>
     */
    public static function refusedNameProvider(): array
    {
        return [
            'parent traversal' => ['../../etc/passwd'],
            'bare parent' => ['..'],
            'current dir' => ['.'],
            'separator' => ['sub/schema.sql'],
            'absolute' => ['/etc/passwd'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('refusedNameProvider')]
    public function testRefusesNamesThatAreNotPlainListedEntries(string $name): void
    {
        $survivor = $this->makeBackup();

        $this->assertSame('Backup not found.', \admin_backup_delete_action($this->settingsWithDir(), $name));
        // Nothing else was touched on the way past.
        $this->assertDirectoryExists($this->dir.$survivor);
    }

    public function testRefusesToDeleteADirectoryHoldingUnknownFiles(): void
    {
        // db_backup_remove() only unlinks .sql/.sql.gz, so a directory with
        // anything else in it survives and the action says so rather than
        // reporting a success that did not happen.
        $name = $this->makeBackup();
        file_put_contents($this->dir.$name.'/notes.txt', 'keep me');

        $result = \admin_backup_delete_action($this->settingsWithDir(), $name);

        $this->assertStringContainsString('Could not delete', $result);
        $this->assertDirectoryExists($this->dir.$name);
        $this->assertFileExists($this->dir.$name.'/notes.txt');
    }

    public function testDeletingOneBackupLeavesTheOthers(): void
    {
        $keep = $this->makeBackup('20240101_000000');
        $drop = $this->makeBackup('20240102_000000');

        \admin_backup_delete_action($this->settingsWithDir(), $drop);

        $this->assertDirectoryDoesNotExist($this->dir.$drop);
        $this->assertDirectoryExists($this->dir.$keep);
    }
}
