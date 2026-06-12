<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class DbApplyPrefixTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/db.apply.prefix.php';
    }

    public function testReturnsUnchangedWhenPrefixIsDefault(): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `phoenix_peers` (`id` int);';
        $this->assertSame($sql, db_apply_prefix($sql, 'phoenix_'));
    }

    public function testReplacesAllOccurrencesOfDefaultPrefix(): void
    {
        $sql = 'SELECT * FROM `phoenix_peers` JOIN `phoenix_torrents` ON 1;';
        $expected = 'SELECT * FROM `custom_peers` JOIN `custom_torrents` ON 1;';
        $this->assertSame($expected, db_apply_prefix($sql, 'custom_'));
    }

    public function testHandlesEmptySql(): void
    {
        $this->assertSame('', db_apply_prefix('', 'other_'));
    }

    public function testHandlesSqlWithNoDefaultPrefix(): void
    {
        $sql = 'SELECT 1;';
        $this->assertSame($sql, db_apply_prefix($sql, 'other_'));
    }

    public function testReturnsUnchangedWhenCustomPrefixMatchesDefault(): void
    {
        // When the custom prefix happens to equal 'phoenix_', the fast path
        // returns the original string reference without calling str_replace.
        $sql = 'ALTER TABLE `phoenix_peers` ADD COLUMN `x` int;';
        $this->assertSame($sql, db_apply_prefix($sql, 'phoenix_'));
    }
}
