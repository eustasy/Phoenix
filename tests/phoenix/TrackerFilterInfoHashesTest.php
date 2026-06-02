<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class TrackerFilterInfoHashesTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/tracker.filter.info.hashes.php';
    }

    public function testReturnsAllRequestedWhenAllAllowed(): void
    {
        $allowed = [
            '0123456789abcdef0123456789abcdef01234567',
            '1111111111111111111111111111111111111111',
            '2222222222222222222222222222222222222222',
        ];
        $requested = [
            '0123456789abcdef0123456789abcdef01234567',
            '1111111111111111111111111111111111111111',
        ];
        $this->assertSame($requested, \tracker_filter_info_hashes($requested, $allowed));
    }

    public function testDropsDisallowedAndKeepsRest(): void
    {
        // Partial overlap: the disallowed entry is silently skipped, the
        // allowed ones survive in their original order.
        $allowed = [
            '0123456789abcdef0123456789abcdef01234567',
            '1111111111111111111111111111111111111111',
        ];
        $requested = [
            '0123456789abcdef0123456789abcdef01234567',
            '2222222222222222222222222222222222222222', // not allowed — dropped
            '1111111111111111111111111111111111111111',
        ];
        $this->assertSame(
            [
                '0123456789abcdef0123456789abcdef01234567',
                '1111111111111111111111111111111111111111',
            ],
            \tracker_filter_info_hashes($requested, $allowed),
        );
    }

    public function testReturnsEmptyArrayWhenNoneAllowed(): void
    {
        // Caller treats empty as "Torrent is not allowed."
        $allowed = [
            '0123456789abcdef0123456789abcdef01234567',
        ];
        $requested = [
            '1111111111111111111111111111111111111111',
            '2222222222222222222222222222222222222222',
        ];
        $this->assertSame([], \tracker_filter_info_hashes($requested, $allowed));
    }

    public function testReturnsEmptyArrayWhenInputEmpty(): void
    {
        $this->assertSame([], \tracker_filter_info_hashes([], ['0123456789abcdef0123456789abcdef01234567']));
    }

    public function testReindexesResult(): void
    {
        // array_intersect preserves the keys of the first argument, which
        // would yield a non-zero-indexed array if the surviving entries
        // weren't first. The function should re-index so callers can rely
        // on $result[0] being the first surviving hash.
        $allowed = [
            '1111111111111111111111111111111111111111',
        ];
        $requested = [
            '0123456789abcdef0123456789abcdef01234567', // dropped
            '1111111111111111111111111111111111111111', // kept (originally index 1)
        ];
        $result = \tracker_filter_info_hashes($requested, $allowed);
        $this->assertSame([0 => '1111111111111111111111111111111111111111'], $result);
    }

}
