<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class StatsClientDetectTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/stats.client.detect.php';
    }

    // The function takes a 40-char HEX peer_id (the form that flows through the
    // codebase), so every fixture is the bin2hex() of a real 20-byte peer_id.
    private function hex(string $rawPeerId): string
    {
        $this->assertSame(20, strlen($rawPeerId), 'fixture must be 20 raw bytes');

        return bin2hex($rawPeerId);
    }

    public function testAzureusKnownCodeWithVersion(): void
    {
        $this->assertSame(
            'qBittorrent 4.6.2.0',
            stats_client_detect($this->hex('-qB4620-'.str_repeat('x', 12))),
        );
    }

    public function testAzureusTransmission(): void
    {
        $this->assertSame(
            'Transmission 4.0.0.0',
            stats_client_detect($this->hex('-TR4000-'.str_repeat('a', 12))),
        );
    }

    public function testAzureusLibtorrentUppercaseAndLowercaseAreDistinct(): void
    {
        $this->assertSame(
            'libtorrent 2.0.9.0',
            stats_client_detect($this->hex('-LT2090-'.str_repeat('0', 12))),
        );
        $this->assertSame(
            'libTorrent 0.1.3.0',
            stats_client_detect($this->hex('-lt0130-'.str_repeat('0', 12))),
        );
    }

    public function testAzureusUnicodeNameClient(): void
    {
        $this->assertSame(
            'µTorrent 3.5.5.0',
            stats_client_detect($this->hex('-UT3550-'.str_repeat('z', 12))),
        );
    }

    public function testAzureusNonNumericVersionOmitsVersion(): void
    {
        // Version chars that aren't all digits -> just the client name.
        $this->assertSame(
            'qBittorrent',
            stats_client_detect($this->hex('-qB46AB-'.str_repeat('x', 12))),
        );
    }

    public function testAzureusUnknownCodeFallsBackToLiteralCode(): void
    {
        // An unrecognised two-letter code is returned verbatim (with version).
        $this->assertSame(
            'ZZ 1.2.3.4',
            stats_client_detect($this->hex('-ZZ1234-'.str_repeat('x', 12))),
        );
    }

    public function testAzureusNonPrintableCodeIsUnknown(): void
    {
        // Raw, non-printable code bytes (a malformed/spoofed peer_id) must not
        // surface into the label — they could be non-UTF-8 (blanking the escaped
        // cell) or otherwise garbage, so collapse to 'Unknown'.
        $this->assertSame(
            'Unknown',
            stats_client_detect($this->hex("-\xff\xfe0000-".str_repeat('x', 12))),
        );
    }

    public function testAzureusMetacharCodeIsUnknown(): void
    {
        // HTML metacharacters aren't alphanumeric, so a code containing them is
        // never surfaced (the views escape too, but this stops it at the source
        // and keeps the stored events.client label clean).
        $this->assertSame(
            'Unknown',
            stats_client_detect($this->hex('-<>0000-'.str_repeat('x', 12))),
        );
    }

    public function testShadowsStyle(): void
    {
        // 'T03I-...' -> BitTornado (Shadow's encoding, single leading letter).
        $this->assertSame(
            'BitTornado',
            stats_client_detect($this->hex('T03I-'.str_repeat('-', 15))),
        );
    }

    public function testShadowsStyleAbc(): void
    {
        $this->assertSame(
            'ABC',
            stats_client_detect($this->hex('A--3-'.str_repeat('-', 15))),
        );
    }

    public function testUnknownShadowsLetterIsUnknown(): void
    {
        // A leading letter not in the Shadow's table, not Azureus-shaped.
        $this->assertSame(
            'Unknown',
            stats_client_detect($this->hex('Z1234'.str_repeat('-', 15))),
        );
    }

    public function testEmptyStringIsUnknown(): void
    {
        $this->assertSame('Unknown', stats_client_detect(''));
    }

    public function testShortHexIsUnknown(): void
    {
        $this->assertSame('Unknown', stats_client_detect('abcd'));
    }

    public function testNonHexIsUnknown(): void
    {
        // 40 chars but not valid hex -> hex2bin would fail; guarded to 'Unknown'.
        $this->assertSame('Unknown', stats_client_detect(str_repeat('g', 40)));
    }

    public function testOddLengthHexIsUnknown(): void
    {
        $this->assertSame('Unknown', stats_client_detect(str_repeat('a', 39)));
    }

    public function testResultFitsClientColumn(): void
    {
        // The events.client column is varchar(64); every label must fit.
        $label = stats_client_detect($this->hex('-qB4620-'.str_repeat('x', 12)));
        $this->assertLessThanOrEqual(64, strlen($label));
    }
}
