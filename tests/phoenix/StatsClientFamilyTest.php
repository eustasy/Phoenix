<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../src/functions/stats.client.family.php';

final class StatsClientFamilyTest extends TestCase
{
    public function testSplitsAVersionOffTheEnd(): void
    {
        $this->assertSame(
            ['family' => 'Transmission', 'version' => '4.1.3.0'],
            stats_client_family('Transmission 4.1.3.0'),
        );
    }

    public function testLabelWithNoSpaceIsAllFamily(): void
    {
        $this->assertSame(['family' => 'Unknown', 'version' => ''], stats_client_family('Unknown'));
    }

    public function testDoesNotSplitAWordThatIsNotAVersion(): void
    {
        // "Unknown" must not become family "Unk" version "nown", and a client
        // whose name contains a space keeps it.
        $this->assertSame(
            ['family' => 'µTorrent for Mac', 'version' => ''],
            stats_client_family('µTorrent for Mac'),
        );
        $this->assertSame(
            ['family' => 'Tribler (versions >= 6.1.0)', 'version' => ''],
            stats_client_family('Tribler (versions >= 6.1.0)'),
        );
    }

    public function testSplitsOnlyTheFinalSpaceSoMultiWordNamesSurvive(): void
    {
        $this->assertSame(
            ['family' => 'µTorrent for Mac', 'version' => '2.2.1.0'],
            stats_client_family('µTorrent for Mac 2.2.1.0'),
        );
    }

    public function testVersionMustStartWithADigit(): void
    {
        // A tail of digits and dots only; 'v4.1' is not one.
        $this->assertSame(['family' => 'Client v4.1', 'version' => ''], stats_client_family('Client v4.1'));
        $this->assertSame(['family' => 'Client', 'version' => '4'], stats_client_family('Client 4'));
    }

    public function testTrailingSpaceYieldsNoVersion(): void
    {
        // The empty tail is not a version, so the label is kept whole —
        // trailing space and all, rather than being silently trimmed.
        $this->assertSame(['family' => 'Client ', 'version' => ''], stats_client_family('Client '));
    }

    public function testEmptyLabelIsHandled(): void
    {
        $this->assertSame(['family' => '', 'version' => ''], stats_client_family(''));
    }
}
