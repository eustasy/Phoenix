<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ScrapeBuildWhereClauseTest extends TestCase
{
    public function testBuildWhereClauseWithSingleHash()
    {
        require_once __DIR__.'/../../src/functions/scrape.build.where.clause.php';

        $hash = str_repeat('a', 40);
        $result = scrape_build_where_clause([$hash]);

        $this->assertSame('WHERE `p`.`info_hash`=?', $result['where']);
        $this->assertSame([$hash], $result['params']);
    }

    public function testBuildWhereClauseWithMultipleHashes()
    {
        require_once __DIR__.'/../../src/functions/scrape.build.where.clause.php';

        $hashes = [
            str_repeat('a', 40),
            str_repeat('b', 40),
            str_repeat('c', 40),
        ];

        $result = scrape_build_where_clause($hashes);

        $this->assertSame(
            'WHERE `p`.`info_hash`=? OR `p`.`info_hash`=? OR `p`.`info_hash`=?',
            $result['where'],
        );
        // One placeholder per hash, hashes returned in order for positional bind.
        $this->assertSame($hashes, $result['params']);
    }

    public function testBuildWhereClauseWithEmptyArray()
    {
        require_once __DIR__.'/../../src/functions/scrape.build.where.clause.php';

        // An empty hash list must not produce a bare 'WHERE' — that would be
        // concatenated onto the model's SELECT and cause a syntax error.
        $result = scrape_build_where_clause([]);

        $this->assertSame('', $result['where']);
        $this->assertSame([], $result['params']);
    }

}
