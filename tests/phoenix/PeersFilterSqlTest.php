<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../src/model/peers.filter.sql.php';

final class PeersFilterSqlTest extends TestCase
{
    public function testNoFilterProducesNoWhereClause(): void
    {
        // The unfiltered listing must not pay for a WHERE it does not need,
        // and peers_count() branches on this being empty.
        $filter = peers_filter_sql('');

        $this->assertSame('', $filter['where']);
        $this->assertSame([], $filter['params']);
    }

    public function testSearchBindsOneParameterPerSearchableColumn(): void
    {
        $filter = peers_filter_sql('boxer');

        $this->assertStringContainsString('p.`ipv4` LIKE ?', $filter['where']);
        $this->assertStringContainsString('t.`name` LIKE ?', $filter['where']);
        // Four columns, four placeholders, four identical bound values — a
        // mismatch here is a bind error at runtime, not a wrong result.
        $this->assertSame(4, substr_count($filter['where'], '?'));
        $this->assertCount(4, $filter['params']);
        $this->assertSame(['%boxer%', '%boxer%', '%boxer%', '%boxer%'], $filter['params']);
    }

    public function testSearchIsTrimmedAndEmptyAfterTrimmingIsNoFilter(): void
    {
        $this->assertSame('%boxer%', peers_filter_sql('  boxer  ')['params'][0]);
        $this->assertSame('', peers_filter_sql("   \t ")['where']);
    }

    public function testEscapesLikeWildcardsSoTheyMatchThemselves(): void
    {
        // Without this a search for "_" matches every single character and a
        // search for "%" matches everything — the filter would silently stop
        // filtering.
        $this->assertSame('%50\\%\\_off%', peers_filter_sql('50%_off')['params'][0]);
        // A literal backslash is escaped first, so it cannot form an escape
        // sequence with the character that follows it.
        $this->assertSame('%a\\\\b%', peers_filter_sql('a\\b')['params'][0]);
    }

    public function testStateFiltersOnlyForTheTwoValidFlags(): void
    {
        $this->assertStringContainsString('p.`state` = ?', peers_filter_sql('', 1)['where']);
        $this->assertSame([1], peers_filter_sql('', 1)['params']);
        $this->assertSame([0], peers_filter_sql('', 0)['params']);
        // -1 is "either", and anything else is not a state this table stores.
        $this->assertSame('', peers_filter_sql('', -1)['where']);
        $this->assertSame('', peers_filter_sql('', 7)['where']);
    }

    public function testInfoHashNarrowsToOneSwarm(): void
    {
        $hash = str_repeat('a', 40);
        $filter = peers_filter_sql('', -1, $hash);

        $this->assertStringContainsString('p.`info_hash` = ?', $filter['where']);
        $this->assertSame([$hash], $filter['params']);
    }

    public function testClausesCombineWithAndInParameterOrder(): void
    {
        // The params must arrive in the order the placeholders appear, or the
        // values bind to the wrong columns.
        $filter = peers_filter_sql('boxer', 1, str_repeat('b', 40));

        $this->assertSame(2, substr_count($filter['where'], ' AND '));
        $this->assertStringStartsWith(' WHERE ', $filter['where']);
        $this->assertSame(
            ['%boxer%', '%boxer%', '%boxer%', '%boxer%', 1, str_repeat('b', 40)],
            $filter['params'],
        );
    }

    public function testSearchTermIsNeverInterpolatedIntoTheClause(): void
    {
        // The clause is a constant shape whatever the input: injection is
        // impossible because the value never reaches the SQL string.
        $filter = peers_filter_sql("'; DROP TABLE peers; --");

        $this->assertStringNotContainsString('DROP', $filter['where']);
        $this->assertSame(peers_filter_sql('x')['where'], $filter['where']);
    }
}
