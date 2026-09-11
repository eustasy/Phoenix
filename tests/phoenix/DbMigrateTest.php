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
        // With no migration files shipped there is nothing to apply, but
        // db_migrate must still report success.
        $this->assertTrue(db_migrate(self::$connection, self::$settings));
    }

    public function testIsIdempotent(): void
    {
        // db_migrate keeps no bookkeeping and re-runs every file every time,
        // so repeated calls must behave identically.
        $this->assertTrue(db_migrate(self::$connection, self::$settings));
        $this->assertTrue(db_migrate(self::$connection, self::$settings));
    }

    public function testDebugPrintsSuccessMessage(): void
    {
        ob_start();
        $ok = db_migrate(self::$connection, self::$settings, true);
        $output = ob_get_clean();

        $this->assertTrue($ok);
        $this->assertStringContainsString('Database Migration successful.', $output);
    }

    public function testShippedMigrationsDirectoryIsEmpty(): void
    {
        // 5.0 is a clean-install release: every 3.x/4.x migration is folded
        // into sql/*.sql, so db_create alone produces the finished schema and
        // db_migrate has nothing to apply. It must still report success.
        $this->assertSame([], glob(__DIR__.'/../../sql/migrations/*.sql'));
        $this->assertTrue(db_migrate(self::$connection, self::$settings));
    }

    public function testRunsAFileAndRewritesTheDefaultPrefix(): void
    {
        // Files use the literal `phoenix_` prefix, which db_migrate rewrites to
        // the install's own before executing — here the TESTING_ one.
        $migrationsDir = __DIR__.'/../../sql/migrations';
        $tmpFile = $migrationsDir.'/9999-99-99-test-prefix.sql';
        file_put_contents(
            $tmpFile,
            '-- A comment; with a semicolon in it.'.PHP_EOL.
            'ALTER TABLE `phoenix_peers` ADD COLUMN IF NOT EXISTS `migrate_probe` int;',
        );

        try {
            $this->assertTrue(db_migrate(self::$connection, self::$settings));

            $result = mysqli_query(
                self::$connection,
                'SELECT COLUMN_NAME FROM `information_schema`.`COLUMNS` '.
                'WHERE TABLE_SCHEMA = \''.self::$settings['db_name'].'\' '.
                'AND TABLE_NAME = \''.self::$settings['db_prefix'].'peers\' '.
                'AND COLUMN_NAME = \'migrate_probe\';',
            );
            $this->assertNotFalse($result);
            $this->assertSame(1, mysqli_num_rows($result));
        } finally {
            unlink($tmpFile);
            mysqli_query(
                self::$connection,
                'ALTER TABLE `'.self::$settings['db_prefix'].'peers` '.
                'DROP COLUMN IF EXISTS `migrate_probe`;',
            );
        }
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
