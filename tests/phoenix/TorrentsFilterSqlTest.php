<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../src/model/torrents.filter.sql.php';

final class TorrentsFilterSqlTest extends TestCase
{
    public function testNoFilterProducesNoWhereClause(): void
    {
        // torrents_count() branches on this to skip the join entirely.
        $filter = torrents_filter_sql('');

        $this->assertSame('', $filter['where']);
        $this->assertSame([], $filter['params']);
    }

    public function testSearchReachesTheMetaColumnsTheTableDoesNotRender(): void
    {
        // filename, files, trackers and webseeds were searchable when the whole
        // table was in the browser; paging the listing would have taken that
        // away if the SQL did not cover them.
        $filter = torrents_filter_sql('ubuntu');

        foreach (['name', 'user', 'info_hash', 'filename', 'files', 'trackers', 'webseeds'] as $column) {
            $this->assertStringContainsString('t.`'.$column.'` LIKE ?', $filter['where']);
        }
        $this->assertSame(7, substr_count($filter['where'], '?'));
        $this->assertCount(7, $filter['params']);
        $this->assertSame('%ubuntu%', $filter['params'][0]);
    }

    public function testEscapesLikeWildcardsSoTheyMatchThemselves(): void
    {
        $this->assertSame('%50\\%\\_off%', torrents_filter_sql('50%_off')['params'][0]);
        $this->assertSame('%a\\\\b%', torrents_filter_sql('a\\b')['params'][0]);
    }

    public function testListedFiltersOnlyForTheTwoValidFlags(): void
    {
        $this->assertSame([1], torrents_filter_sql('', 1)['params']);
        $this->assertSame([0], torrents_filter_sql('', 0)['params']);
        $this->assertSame('', torrents_filter_sql('', -1)['where']);
    }

    public function testInfoHashNarrowsToOneTorrent(): void
    {
        // The Bandwidth drill-down is this filter, not a second view.
        $hash = str_repeat('c', 40);
        $filter = torrents_filter_sql('', -1, $hash);

        $this->assertStringContainsString('t.`info_hash` = ?', $filter['where']);
        $this->assertSame([$hash], $filter['params']);
    }

    public function testClausesCombineInParameterOrder(): void
    {
        $hash = str_repeat('d', 40);
        $filter = torrents_filter_sql('ubuntu', 1, $hash);

        $this->assertStringStartsWith(' WHERE ', $filter['where']);
        $this->assertSame(9, count($filter['params']));
        $this->assertSame(1, $filter['params'][7]);
        $this->assertSame($hash, $filter['params'][8]);
    }

    public function testSearchTermIsNeverInterpolatedIntoTheClause(): void
    {
        $filter = torrents_filter_sql("'; DROP TABLE torrents; --");

        $this->assertStringNotContainsString('DROP', $filter['where']);
        $this->assertSame(torrents_filter_sql('x')['where'], $filter['where']);
    }
}
