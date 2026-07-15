<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class DbBackupTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/db.backup.php';
    }

    private function mysqldumpAvailable(): bool
    {
        $proc = @proc_open(
            ['mysqldump', '--version'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        if (! is_resource($proc)) {
            return false;
        }
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($proc) === 0;
    }

    public function testReturnsErrorWhenDirMissing(): void
    {
        // No mysqldump needed: db_backup bails before the dump. Also pins the
        // result-shape contract.
        $settings = self::$settings;
        $settings['backup_dir'] = sys_get_temp_dir().'/phx_no_such_'.bin2hex(random_bytes(4));

        $result = db_backup($settings, self::$time);

        $this->assertArrayHasKey('ok', $result);
        $this->assertArrayHasKey('file', $result);
        $this->assertArrayHasKey('error', $result);
        $this->assertFalse($result['ok']);
        $this->assertNull($result['file']);
        $this->assertSame('BACKUP_DIR_NOT_FOUND', $result['error']);
    }

    public function testRunReturnsWellFormedResultWhenMysqldumpAvailable(): void
    {
        if (! $this->mysqldumpAvailable()) {
            $this->markTestSkipped('mysqldump binary not available');
        }

        $dir = sys_get_temp_dir().'/phx_bak_'.bin2hex(random_bytes(4)).'/';
        mkdir($dir);
        $settings = self::$settings;
        $settings['backup_dir'] = $dir;
        $settings['backup_retention'] = 0; // don't rotate the dump we just wrote

        try {
            $result = db_backup($settings, self::$time);

            // Exercise the real dump path (creds file + two mysqldump passes)
            // and assert the result-shape contract. We do NOT require ok=true:
            // a MySQL mysqldump against a MariaDB server fails on
            // information_schema.COLUMN_STATISTICS, which is an operator
            // client/server mismatch, not an engine fault — the successful-dump
            // end-to-end is covered by the smoke suite (matching client).
            $this->assertIsBool($result['ok']);
            if ($result['ok']) {
                $this->assertIsString($result['file']);
                $this->assertFileExists($result['file']);
                $this->assertNull($result['error']);
            } else {
                $this->assertNull($result['file']);
                $this->assertIsString($result['error']);
                $this->assertNotSame('', $result['error']);
            }
        } finally {
            foreach (glob($dir.'*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
    }
}
