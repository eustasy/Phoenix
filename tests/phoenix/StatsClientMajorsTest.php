<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../src/functions/stats.client.majors.php';

final class StatsClientMajorsTest extends TestCase
{
    public function testGroupsPointReleasesUnderTheirMajor(): void
    {
        $majors = stats_client_majors(['4.1.3.0' => 49, '4.0.6' => 12, '3.0.0' => 7]);

        // Cast: PHP reads a numeric-looking key back as an int.
        $this->assertSame(['4', '3'], array_map('strval', array_keys($majors)));
        $this->assertSame(61, $majors['4']['total']);
        $this->assertSame(7, $majors['3']['total']);
    }

    public function testKeepsTheExactVersionsBehindEachMajor(): void
    {
        $majors = stats_client_majors(['4.0.6' => 12, '4.1.3.0' => 49]);

        // Biggest first, so a caller can render without re-sorting.
        $this->assertSame(['4.1.3.0' => 49, '4.0.6' => 12], $majors['4']['versions']);
    }

    public function testOrdersGroupsByTotalNotByVersion(): void
    {
        // 9 sorts after 10 numerically and before it as a string; neither
        // matters — the biggest group leads.
        $majors = stats_client_majors(['10.2' => 1, '9.9' => 40]);

        $this->assertSame(['9', '10'], array_map('strval', array_keys($majors)));
    }

    public function testVersionlessLabelStaysVersionless(): void
    {
        // An unrecognised client charts as one solid bar rather than vanishing.
        $majors = stats_client_majors(['' => 30]);

        $this->assertSame([''], array_map('strval', array_keys($majors)));
        $this->assertSame(30, $majors['']['total']);
    }

    public function testNonNumericVersionIsKeptWholeRatherThanMerged(): void
    {
        // "Tribler (versions >= 6.1.0)" style labels are not version numbers
        // this can reason about, so they get their own group instead of being
        // folded into something they do not belong to.
        $majors = stats_client_majors(['versions >= 6.1.0' => 3, '2.1' => 5]);

        $this->assertArrayHasKey('versions >= 6.1.0', $majors);
        $this->assertArrayHasKey('2', $majors);
        $this->assertSame(5, $majors['2']['total']);
    }
}
