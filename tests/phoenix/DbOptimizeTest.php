<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class DbOptimizeTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/db.optimize.php';
    }

    public function testOptimizesDefaultTables(): void
    {
        // db_optimize touches real tables; there is no clean alternative that
        // preserves coverage, so we rely on idempotency of CHECK/ANALYZE/REPAIR/OPTIMIZE.
        $this->assertTrue(db_optimize(self::$connection, self::$settings, self::$time));
    }

    public function testEmptyTableSetShortCircuits(): void
    {
        // With no specific table and defaults disabled, $tables is empty.
        // The function should short-circuit to true rather than passing an empty SQL
        // string to mysqli_multi_query (which would return false).
        $this->assertTrue(db_optimize(self::$connection, self::$settings, self::$time, false, false));
    }

    public function testOptimizesExplicitTableOnly(): void
    {
        // Pass an explicit $table with $and_default=false so only the
        // `$tables[] = $table;` branch fires and the three default-table
        // pushes are skipped. Same idempotency reasoning as the default case.
        $this->assertTrue(db_optimize(self::$connection, self::$settings, self::$time, 'peers', false));
    }

    public function testOptimizesExplicitTableAlongsideDefaults(): void
    {
        // $table set AND $and_default=true means both branches fire; the
        // explicit table is added before the three defaults. Running CHECK/
        // ANALYZE/REPAIR/OPTIMIZE twice on `peers` is harmless.
        $this->assertTrue(db_optimize(self::$connection, self::$settings, self::$time, 'peers', true));
    }

    public function testFailsWhenATableDoesNotExist(): void
    {
        // A rebuild that cannot run must not report success — the failure
        // arrives as a row inside the result set, not as a query error.
        $this->assertFalse(db_optimize(self::$connection, self::$settings, self::$time, '__no_such_table__', false));
    }

    public function testDoesNotLogARunItDidNotComplete(): void
    {
        $prefix = self::$settings['db_prefix'];
        $before = mysqli_fetch_assoc(mysqli_query(
            self::$connection,
            'SELECT COUNT(*) AS `n` FROM `'.$prefix.'task_runs` WHERE `name` = \'optimize\';',
        ));

        db_optimize(self::$connection, self::$settings, self::$time, '__no_such_table__', false);

        $after = mysqli_fetch_assoc(mysqli_query(
            self::$connection,
            'SELECT COUNT(*) AS `n` FROM `'.$prefix.'task_runs` WHERE `name` = \'optimize\';',
        ));
        $this->assertIsArray($before);
        $this->assertIsArray($after);
        $this->assertSame($before['n'], $after['n']);
    }
}
