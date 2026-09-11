<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class AdminMigrateActionTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/controller/admin.migrate.php';
    }

    public function testReturnsSuccessMessageOnMigrateSuccess(): void
    {
        // 5.0 ships no migration files, so db_migrate has nothing to apply
        // and still returns true — the success branch.
        $result = admin_migrate_action(self::$connection, self::$settings, self::$time);
        $this->assertSame('Your schema has been upgraded.', $result);
    }

    public function testReturnsFailureMessageWhenMigrationFails(): void
    {
        // Write a temporary migration file that targets a non-existent table
        // so db_migrate returns false and the failure-message branch fires.
        $migrationsDir = __DIR__.'/../../sql/migrations';
        $tmpFile = $migrationsDir.'/9999-99-99-adminmigrate-test-fail.sql';
        file_put_contents($tmpFile, 'ALTER TABLE `phoenix___no_such_table___` ADD COLUMN IF NOT EXISTS `x` int;');

        mysqli_report(MYSQLI_REPORT_OFF);
        try {
            $result = admin_migrate_action(self::$connection, self::$settings, self::$time);
            $this->assertSame('Could not upgrade the schema.', $result);
        } finally {
            unlink($tmpFile);
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        }
    }
}
