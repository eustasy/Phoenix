<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class DbBackupRemoveTest extends PhoenixTestCase
{
    private string $dir = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/db.backup.remove.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/phx_bakrm_'.bin2hex(random_bytes(4)).'/';
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

    public function testRemovesADirectoryBackupAndItsDumps(): void
    {
        $backup = $this->dir.'phoenix.20240101_000000';
        mkdir($backup);
        file_put_contents($backup.'/schema.sql', '-- sql');
        file_put_contents($backup.'/torrents.sql.gz', 'gz');

        $this->assertTrue(\db_backup_remove($backup));
        $this->assertDirectoryDoesNotExist($backup);
    }

    public function testRemovesALegacySingleFileBackup(): void
    {
        $file = $this->dir.'phoenix.20240101_0000.sql.gz';
        file_put_contents($file, 'gz');

        $this->assertTrue(\db_backup_remove($file));
        $this->assertFileDoesNotExist($file);
    }

    public function testMissingPathReportsSuccess(): void
    {
        // Rotation's goal is that the backup is gone; already-gone satisfies it.
        $this->assertTrue(\db_backup_remove($this->dir.'never_existed'));
    }

    public function testLeavesADirectoryHoldingUnrecognisedFiles(): void
    {
        // Deliberately not a recursive delete: this only unlinks .sql/.sql.gz,
        // so anything else keeps its directory rather than being destroyed by a
        // rotation that was only ever meant to remove dumps.
        $backup = $this->dir.'phoenix.20240101_000000';
        mkdir($backup);
        file_put_contents($backup.'/schema.sql', '-- sql');
        file_put_contents($backup.'/notes.txt', 'keep me');

        $this->assertFalse(\db_backup_remove($backup));
        $this->assertDirectoryExists($backup);
        $this->assertFileExists($backup.'/notes.txt');
    }
}
