<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class DbCreateTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/db.create.php';
    }

    public function testCreatesTablesOnFirstCall(): void
    {
        $this->assertTrue(db_create(self::$connection, self::$settings));
        foreach (['peers', 'tasks', 'task_runs', 'torrents'] as $table) {
            $check = mysqli_query(
                self::$connection,
                'SELECT TABLE_NAME FROM `information_schema`.`TABLES` '.
                'WHERE TABLE_SCHEMA = \''.self::$settings['db_name'].'\' '.
                'AND TABLE_NAME = \''.self::$settings['db_prefix'].$table.'\';',
            );
            $this->assertNotFalse($check);
            $this->assertSame(1, mysqli_num_rows($check));
        }
    }

    public function testSchemaIsCompleteWithoutMigrations(): void
    {
        // db_create alone must produce the finished schema: with sql/migrations
        // empty there is nothing to repair a column missing from a schema file,
        // so a fresh install would silently lose it.
        $this->assertTrue(db_create(self::$connection, self::$settings));

        $expected = [
            'torrents' => ['user', 'filename', 'files', 'trackers', 'webseeds'],
            'peers' => ['uploaded', 'downloaded', 'left'],
        ];
        foreach ($expected as $table => $columns) {
            $result = mysqli_query(
                self::$connection,
                'SELECT COLUMN_NAME FROM `information_schema`.`COLUMNS` '.
                'WHERE TABLE_SCHEMA = \''.self::$settings['db_name'].'\' '.
                'AND TABLE_NAME = \''.self::$settings['db_prefix'].$table.'\' '.
                'AND COLUMN_NAME IN (\''.implode('\', \'', $columns).'\');',
            );
            $this->assertNotFalse($result);
            $this->assertSame(count($columns), mysqli_num_rows($result), $table);
        }
    }

    public function testPeerLeftColumnIsSigned(): void
    {
        // `left` must be SIGNED so the -1 "bytes remaining unknown" sentinel
        // from an announce without `left` can be stored.
        $result = mysqli_query(
            self::$connection,
            'SELECT `COLUMN_TYPE` FROM `information_schema`.`COLUMNS` '.
            'WHERE TABLE_SCHEMA = \''.self::$settings['db_name'].'\' '.
            'AND TABLE_NAME = \''.self::$settings['db_prefix'].'peers\' '.
            'AND COLUMN_NAME = \'left\';',
        );
        $this->assertNotFalse($result);
        $row = mysqli_fetch_assoc($result);
        $this->assertNotNull($row);
        $this->assertStringNotContainsStringIgnoringCase('unsigned', (string) $row['COLUMN_TYPE']);
    }

    public function testIsIdempotent(): void
    {
        // IF NOT EXISTS means a second call must be a no-op, not an error.
        $this->assertTrue(db_create(self::$connection, self::$settings));
        $this->assertTrue(db_create(self::$connection, self::$settings));
    }

    public function testDebugPrintsSuccessMessage(): void
    {
        // Bootstrap already ran db_create, so this is the idempotent path:
        // every CREATE TABLE IF NOT EXISTS succeeds and the success line at
        // the bottom of the function fires.
        ob_start();
        $ok = db_create(self::$connection, self::$settings, true);
        $output = ob_get_clean();

        $this->assertTrue($ok);
        $this->assertSame('Database Creation successful.'.PHP_EOL, $output);
    }

    public function testDebugPrintsQueryErrorAndFailureMessage(): void
    {
        // Force CREATE TABLE to fail by overflowing MySQL's 64-char
        // identifier limit. PHP 8.1+ defaults mysqli_report to
        // REPORT_ERROR|REPORT_STRICT, which would throw mysqli_sql_exception
        // and bypass the !$result branch entirely; flip to OFF for the test
        // so mysqli_query returns false the way the function expects.
        mysqli_report(MYSQLI_REPORT_OFF);

        $settings = self::$settings;
        $settings['db_prefix'] = str_repeat('x', 60).'_'; // 61 chars + 'peers' = 66, exceeds 64

        try {
            ob_start();
            $ok = db_create(self::$connection, $settings, true);
            $output = ob_get_clean();
        } finally {
            // Restore the PHP 8.1+ default so subsequent tests see the
            // behaviour they expect.
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        }

        $this->assertFalse($ok);
        $this->assertStringContainsString('Error #', $output);
        $this->assertStringContainsString('Database Creation failed.', $output);
    }

    public function testDebugPrintsSchemaReadError(): void
    {
        // Move sql/peers.sql aside so file_get_contents returns false on
        // the peers iteration, exercising the "Could not read schema file"
        // branch. tasks and torrents still succeed under a unique prefix
        // which we clean up in the finally to avoid leaking tables.
        $sqlPath = __DIR__.'/../../sql/peers.sql';
        $sqlBackup = $sqlPath.'.dbcreate_test_bak';
        $this->assertTrue(rename($sqlPath, $sqlBackup));

        $settings = self::$settings;
        $settings['db_prefix'] = 'phoenix_dbcreate_schema_test_';

        try {
            ob_start();
            $ok = db_create(self::$connection, $settings, true);
            $output = ob_get_clean();
        } finally {
            rename($sqlBackup, $sqlPath);
            require_once __DIR__.'/../../src/model/db.drop.php';
            db_drop_table(self::$connection, $settings, 'tasks');
            db_drop_table(self::$connection, $settings, 'torrents');
        }

        $this->assertFalse($ok);
        $this->assertStringContainsString('Could not read schema file', $output);
        $this->assertStringContainsString('peers.sql', $output);
        $this->assertStringContainsString('Database Creation failed.', $output);
    }

}
