<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class PeerParseAnnounceOptionalTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/peer.parse.announce.optional.php';
    }

    /** @return array<string, mixed> */
    private function settingsFor(bool $defaultCompact = false): array
    {
        return [
            'default_compact' => $defaultCompact,
            'default_peers' => 50,
            'max_peers' => 200,
        ];
    }

    // ---- left / state ----

    public function testLeftMissingYieldsMinusOneAndLeechingState(): void
    {
        $result = peer_parse_announce_optional([], $this->settingsFor());
        $this->assertSame(-1, $result['left']);
        $this->assertSame(0, $result['state']);
    }

    public function testLeftZeroYieldsSeedingState(): void
    {
        $result = peer_parse_announce_optional(['left' => '0'], $this->settingsFor());
        $this->assertSame(0, $result['left']);
        $this->assertSame(1, $result['state']);
    }

    public function testLeftPositiveYieldsLeechingState(): void
    {
        $result = peer_parse_announce_optional(['left' => '12345'], $this->settingsFor());
        $this->assertSame(12345, $result['left']);
        $this->assertSame(0, $result['state']);
    }

    // ---- compact ----

    public function testCompactExplicitOneYieldsOne(): void
    {
        $result = peer_parse_announce_optional(['compact' => '1'], $this->settingsFor());
        $this->assertSame(1, $result['compact']);
    }

    public function testCompactExplicitZeroOverridesDefaultTrue(): void
    {
        $result = peer_parse_announce_optional(['compact' => '0'], $this->settingsFor(true));
        $this->assertSame(0, $result['compact']);
    }

    public function testCompactMissingFollowsDefaultTrue(): void
    {
        $result = peer_parse_announce_optional([], $this->settingsFor(true));
        $this->assertSame(1, $result['compact']);
    }

    public function testCompactMissingFollowsDefaultFalse(): void
    {
        $result = peer_parse_announce_optional([], $this->settingsFor(false));
        $this->assertSame(0, $result['compact']);
    }

    // ---- no_peer_id ----

    public function testNoPeerIdMissingYieldsZero(): void
    {
        $result = peer_parse_announce_optional([], $this->settingsFor());
        $this->assertSame(0, $result['no_peer_id']);
    }

    public function testNoPeerIdPositiveYieldsOne(): void
    {
        $result = peer_parse_announce_optional(['no_peer_id' => '1'], $this->settingsFor());
        $this->assertSame(1, $result['no_peer_id']);
    }

    public function testNoPeerIdZeroYieldsZero(): void
    {
        $result = peer_parse_announce_optional(['no_peer_id' => '0'], $this->settingsFor());
        $this->assertSame(0, $result['no_peer_id']);
    }

    // ---- uploaded / downloaded ----

    public function testUploadedMissingYieldsZero(): void
    {
        $result = peer_parse_announce_optional([], $this->settingsFor());
        $this->assertSame(0, $result['uploaded']);
    }

    public function testUploadedNegativeYieldsZero(): void
    {
        $result = peer_parse_announce_optional(['uploaded' => '-50'], $this->settingsFor());
        $this->assertSame(0, $result['uploaded']);
    }

    public function testUploadedPositiveYieldsValue(): void
    {
        $result = peer_parse_announce_optional(['uploaded' => '12345'], $this->settingsFor());
        $this->assertSame(12345, $result['uploaded']);
    }

    public function testDownloadedMissingYieldsZero(): void
    {
        $result = peer_parse_announce_optional([], $this->settingsFor());
        $this->assertSame(0, $result['downloaded']);
    }

    public function testDownloadedNegativeYieldsZero(): void
    {
        $result = peer_parse_announce_optional(['downloaded' => '-1'], $this->settingsFor());
        $this->assertSame(0, $result['downloaded']);
    }

    public function testDownloadedPositiveYieldsValue(): void
    {
        $result = peer_parse_announce_optional(['downloaded' => '999'], $this->settingsFor());
        $this->assertSame(999, $result['downloaded']);
    }

    // ---- numwant ----

    public function testNumwantMissingFallsBackToDefaultPeers(): void
    {
        $result = peer_parse_announce_optional([], $this->settingsFor());
        $this->assertSame(50, $result['numwant']);
    }

    public function testNumwantWithinRangeIsKept(): void
    {
        $result = peer_parse_announce_optional(['numwant' => '100'], $this->settingsFor());
        $this->assertSame(100, $result['numwant']);
    }

    public function testNumwantAboveMaxClampsToMax(): void
    {
        $result = peer_parse_announce_optional(['numwant' => '500'], $this->settingsFor());
        $this->assertSame(200, $result['numwant']);
    }

    public function testNumwantZeroClampsToMax(): void
    {
        $result = peer_parse_announce_optional(['numwant' => '0'], $this->settingsFor());
        $this->assertSame(200, $result['numwant']);
    }

    public function testNumwantNegativeClampsToMax(): void
    {
        $result = peer_parse_announce_optional(['numwant' => '-1'], $this->settingsFor());
        $this->assertSame(200, $result['numwant']);
    }

}
