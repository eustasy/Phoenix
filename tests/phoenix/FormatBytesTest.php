<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class FormatBytesTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__.'/../../src/functions/format.bytes.php';
    }

    public function testFormatsBinaryUnits(): void
    {
        $cases = [
            [0, '0 B'],
            [512, '512 B'],
            [1023, '1023 B'],
            [1024, '1.0 KB'],
            [901120, '880 KB'],
            [702545920, '670 MB'],
            [1073741824, '1.0 GB'],
            [5150212096, '4.8 GB'],
            [6442450944, '6.0 GB'],
            [9876543210, '9.2 GB'],
        ];

        foreach ($cases as [$bytes, $expected]) {
            $this->assertSame($expected, format_bytes($bytes), $bytes.' bytes');
        }
    }

    public function testNegativeClampsToZero(): void
    {
        $this->assertSame('0 B', format_bytes(-100));
    }

    public function testDecimalOnlyBelowTen(): void
    {
        // >= 10 in a unit drops the decimal; < 10 keeps one place.
        $this->assertSame('9.8 KB', format_bytes(10000));
        $this->assertSame('98 KB', format_bytes(100000));
    }
}
