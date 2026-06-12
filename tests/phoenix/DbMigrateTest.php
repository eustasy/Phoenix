<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class DbMigrateTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/db.migrate.php';
    }

    public function testRunsMigrationsSuccessfully(): void
    {
        // The test DB was created by bootstrap with the current schema, so
        // every ADD COLUMN IF NOT EXISTS is a no-op — but db_migrate must
        // still return true.
        $this->assertTrue(db_migrate(self::$connection, self::$settings));
    }

    public function testIsIdempotent(): void
    {
        // Running db_migrate twice must both return true; idempotent statements
        // (ADD COLUMN IF NOT EXISTS) never produce errors on repeat runs.
        $this->assertTrue(db_migrate(self::$connection, self::$settings));
        $this->assertTrue(db_migrate(self::$connection, self::$settings));
    }

    public function testMetaColumnsExistAfterMigration(): void
    {
        // After running migrations the torrent meta columns introduced in the
        // 4.1 migration must be present in the TESTING_-prefixed table.
        db_migrate(self::$connection, self::$settings);

        $result = mysqli_query(
            self::$connection,
            'SELECT COLUMN_NAME FROM `information_schema`.`COLUMNS` '.
            'WHERE TABLE_SCHEMA = \''.self::$settings['db_name'].'\' '.
            'AND TABLE_NAME = \''.self::$settings['db_prefix'].'torrents\''.
            ' AND COLUMN_NAME IN (\'user\', \'filename\', \'files\', \'trackers\', \'webseeds\');',
        );
        $this->assertNotFalse($result);
        $this->assertSame(5, mysqli_num_rows($result));
    }

    public function testPeerColumnsExistAfterMigration(): void
    {
        // After running migrations the peer columns introduced in the 3.2
        // migration must be present in the TESTING_-prefixed table.
        db_migrate(self::$connection, self::$settings);

        $result = mysqli_query(
            self::$connection,
            'SELECT COLUMN_NAME FROM `information_schema`.`COLUMNS` '.
            'WHERE TABLE_SCHEMA = \''.self::$settings['db_name'].'\' '.
            'AND TABLE_NAME = \''.self::$settings['db_prefix'].'peers\''.
            ' AND COLUMN_NAME IN (\'uploaded\', \'downloaded\');',
        );
        $this->assertNotFalse($result);
        $this->assertSame(2, mysqli_num_rows($result));
    }

    public function testDebugPrintsSuccessMessage(): void
    {
        ob_start();
        $ok = db_migrate(self::$connection, self::$settings, true);
        $output = ob_get_clean();

        $this->assertTrue($ok);
        $this->assertStringContainsString('Database Migration successful.', $output);
    }

    public function testReturnsTrueWhenNoMigrationFilesExist(): void
    {
        // glob() returns an empty array (not false) when the directory exists
        // but has no matching files. With no files to run there are no failures,
        // so db_migrate should return true. Use a non-existent prefix for the
        // glob pattern by pointing to a temp directory that has no .sql files.
        $tmpDir = sys_get_temp_dir().'/phoenix_migrate_test_empty_'.mt_rand();
        mkdir($tmpDir);

        // Temporarily symlink so db_migrate finds our empty directory.
        $migrationsDir = __DIR__.'/../../sql/migrations';
        $backup = $migrationsDir.'_dbmigrate_empty_test_bak';
        $this->assertTrue(rename($migrationsDir, $backup));
        mkdir($migrationsDir);

        try {
            $ok = db_migrate(self::$connection, self::$settings);
        } finally {
            rmdir($migrationsDir);
            rename($backup, $migrationsDir);
            rmdir($tmpDir);
        }

        $this->assertTrue($ok);
    }

    public function testReturnsFalseWhenStatementFails(): void
    {
        // Force a migration statement to fail by using a settings prefix that
        // points to a non-existent table. We write a temporary migration file
        // whose ALTER TABLE targets a table that does not exist.
        $migrationsDir = __DIR__.'/../../sql/migrations';
        $tmpFile = $migrationsDir.'/9999-99-99-test-fail.sql';
        file_put_contents($tmpFile, 'ALTER TABLE `phoenix___no_such_table___` ADD COLUMN IF NOT EXISTS `x` int;');

        mysqli_report(MYSQLI_REPORT_OFF);
        try {
            ob_start();
            $ok = db_migrate(self::$connection, self::$settings, true);
            $output = ob_get_clean();
        } finally {
            unlink($tmpFile);
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        }

        $this->assertFalse($ok);
        $this->assertStringContainsString('Error #', $output);
        $this->assertStringContainsString('Database Migration failed.', $output);
    }
}
