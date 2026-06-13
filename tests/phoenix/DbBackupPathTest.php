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
        foreach (glob($this->tempDir.'*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
        parent::tearDown();
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
}
