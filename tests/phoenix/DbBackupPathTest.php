<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class DbBackupPathTest extends PhoenixTestCase
{
    private string $tempDir = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/db.backup.path.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir().'/phx_bakpath_'.bin2hex(random_bytes(4)).'/';
        mkdir($this->tempDir, 0o700, true);
    }

    protected function tearDown(): void
    {
        // Backups are directories now, so clear one level of nesting too.
        foreach (glob($this->tempDir.'*') ?: [] as $path) {
            if (is_dir($path)) {
                foreach (glob($path.'/*') ?: [] as $file) {
                    unlink($file);
                }
                rmdir($path);
                continue;
            }
            unlink($path);
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    /** @param list<string> $files */
    private function makeBackupDir(string $name, array $files = ['schema.sql', 'torrents.sql']): string
    {
        mkdir($this->tempDir.$name, 0o700);
        foreach ($files as $file) {
            file_put_contents($this->tempDir.$name.'/'.$file, '-- sql');
        }

        return $name;
    }

    /** @return array<string, mixed> */
    private function settingsWithDir(): array
    {
        $settings = self::$settings;
        $settings['backup_dir'] = $this->tempDir;

        return $settings;
    }

    public function testReturnsFullPathForListedBackup(): void
    {
        $db_name = self::$settings['db_name'];
        $filename = $db_name.'.20240101_0000.sql';
        file_put_contents($this->tempDir.$filename, '-- sql dump');

        $result = \db_backup_path($this->settingsWithDir(), $filename);

        $this->assertSame($this->tempDir.$filename, $result);
    }

    public function testReturnsFalseForUnknownName(): void
    {
        $result = \db_backup_path($this->settingsWithDir(), 'nope.sql');

        $this->assertFalse($result);
    }

    public function testReturnsFalseForTraversalAttempt(): void
    {
        $result = \db_backup_path($this->settingsWithDir(), '../../etc/passwd');

        $this->assertFalse($result);
    }

    public function testReturnsFalseWhenNameHasLeadingSubdirForRealFile(): void
    {
        // Even if the real file exists via the list, a name with a slash must
        // be rejected before lookup (basename guard).
        $db_name = self::$settings['db_name'];
        $filename = $db_name.'.20240101_0000.sql';
        file_put_contents($this->tempDir.$filename, '-- sql dump');

        $result = \db_backup_path($this->settingsWithDir(), 'sub/'.$filename);

        $this->assertFalse($result);
    }

    public function testReturnsFalseForFileNotMatchingPattern(): void
    {
        // A file in the backup dir that doesn't match <db_name>.*.sql is never
        // returned by db_backup_list and therefore can't be resolved here.
        file_put_contents($this->tempDir.'notes.txt', 'notes');

        $result = \db_backup_path($this->settingsWithDir(), 'notes.txt');

        $this->assertFalse($result);
    }

    public function testResolvesAFileInsideADirectoryBackup(): void
    {
        $name = $this->makeBackupDir(self::$settings['db_name'].'.20240101_000000');

        $result = \db_backup_path($this->settingsWithDir(), $name, 'schema.sql');

        $this->assertSame($this->tempDir.$name.'/schema.sql', $result);
    }

    public function testDirectoryBackupWithoutAFileIsRejected(): void
    {
        // The directory itself is not downloadable; the caller must name a dump.
        $name = $this->makeBackupDir(self::$settings['db_name'].'.20240101_000000');

        $this->assertFalse(\db_backup_path($this->settingsWithDir(), $name));
    }

    public function testLegacyBackupNamingAFileIsRejected(): void
    {
        $filename = self::$settings['db_name'].'.20240101_0000.sql';
        file_put_contents($this->tempDir.$filename, '-- sql dump');

        $this->assertFalse(\db_backup_path($this->settingsWithDir(), $filename, 'schema.sql'));
    }

    public function testUnlistedFileInARealBackupIsRejected(): void
    {
        // Present on disk but not reported by db_backup_list(), so not servable.
        $name = $this->makeBackupDir(self::$settings['db_name'].'.20240101_000000');
        file_put_contents($this->tempDir.$name.'/secrets.env', 'nope');

        $this->assertFalse(\db_backup_path($this->settingsWithDir(), $name, 'secrets.env'));
    }

    /**
     * Neither segment may carry a directory component — otherwise a download
     * could be steered out of backup_dir.
     *
     * @return array<string, array{string, string}>
     */
    public static function traversalProvider(): array
    {
        return [
            'parent in name' => ['../../etc/passwd', ''],
            'bare parent as name' => ['..', ''],
            'separator in name' => ['sub/schema.sql', ''],
            'parent in file' => ['%s', '../other/schema.sql'],
            'bare parent as file' => ['%s', '..'],
            'absolute file' => ['%s', '/etc/passwd'],
            'separator in file' => ['%s', 'sub/schema.sql'],
            'empty name' => ['', 'schema.sql'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('traversalProvider')]
    public function testRejectsTraversal(string $name, string $file): void
    {
        $real = $this->makeBackupDir(self::$settings['db_name'].'.20240101_000000');
        // '%s' in the provider means "a genuinely listed backup", so the case
        // tests the file segment rather than failing on an unknown name.
        $name = $name === '%s' ? $real : $name;

        $this->assertFalse(\db_backup_path($this->settingsWithDir(), $name, $file));
    }
}
