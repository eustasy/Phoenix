<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../src/functions/stats.client.chart.php';

final class StatsClientChartTest extends TestCase
{
    public function testGroupsEachFamilyByMajor(): void
    {
        $chart = stats_client_chart(['Transmission' => ['4.1.3.0' => 49, '4.0.6' => 12, '3.0.0' => 7]]);

        $this->assertSame([['Transmission', [['4', 61], ['3', 7]]]], $chart);
    }

    public function testKeepsANumericFamilyInTheOrderGiven(): void
    {
        // PHP holds the family "53" as int 53. As a JSON object key it would be
        // enumerated ahead of every other family in the browser.
        $chart = stats_client_chart(['Transmission' => ['' => 500], '53' => ['' => 2]]);

        $this->assertSame(['Transmission', '53'], array_column($chart, 0));
        $this->assertSame('[["Transmission",[["",500]]],["53",[["",2]]]]', json_encode($chart));
    }

    public function testKeepsMajorsNewestFirstWithTheVersionlessRemainderLast(): void
    {
        // As an object these would come back "3", "4", "" in the browser.
        $chart = stats_client_chart(['qBittorrent' => ['' => 90, '3.2.1' => 5, '4.6.0' => 40]]);

        $this->assertSame([['4', 40], ['3', 5], ['', 90]], $chart[0][1]);
    }

    public function testReturnsAnEmptyListForNoFamilies(): void
    {
        $this->assertSame([], stats_client_chart([]));
        $this->assertSame('[]', json_encode(stats_client_chart([])));
    }
}
