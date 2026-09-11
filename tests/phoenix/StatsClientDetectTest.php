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

    public function testCorrectsCodesThatWereMappedToTheWrongClient(): void
    {
        // Checked against the BEP 20 / BitTorrentSpecification registry after
        // the ledger showed this tracker had seen clients the table named
        // differently. A wrong name is worse than a bare code: it is believed.
        $this->assertSame('BitWombat', \stats_client_detect($this->hex('-BW160M-aaaaaaaaaaaa')));
        $this->assertSame('BiglyBT 1.6.0.0', \stats_client_detect($this->hex('-BI1600-aaaaaaaaaaaa')));
        $this->assertSame('Retriever', \stats_client_detect($this->hex('-RT160M-aaaaaaaaaaaa')));
    }

    public function testNamesMatchTheRegistrySoLabelsMerge(): void
    {
        // These names are the registry's, not the friendlier modern ones, so a
        // label written here folds into the same family as one written by
        // another implementation of the same table — which is what lets the
        // live and all-time client views line up.
        $this->assertSame('DelugeTorrent', \stats_client_detect($this->hex('-DE205z-aaaaaaaaaaaa')));
        $this->assertSame('µTorrent for Mac 2.2.1.0', \stats_client_detect($this->hex('-UM2210-aaaaaaaaaaaa')));
    }

    public function testUnregisteredCodeStaysABareCode(): void
    {
        // A well-formed code the table does not know surfaces as itself.
        // Surfacing "Q7" is honest; guessing a name for it would not be.
        //
        // Deliberately a code no client is known to use: this fixture was
        // '-FL' until Folx was identified and added, at which point the test
        // was asserting the opposite of what it meant.
        $this->assertSame('Q7', \stats_client_detect($this->hex('-Q756FF-aaaaaaaaaaaa')));
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

    public function testIdentifiesCodesAbsentFromBep20(): void
    {
        // Real peers announce these and BEP 20 does not list them, so without
        // an entry an operator sees a bare two-letter code. Sourced from the
        // clients' own identification tables, not a wiki.
        $this->assertSame('Zona 3.0.0.8', stats_client_detect($this->hex('-ZO3008-abcdefghijkl')));
        $this->assertSame('Folx', stats_client_detect($this->hex('-FL56FF-abcdefghijkl')));
    }

    public function testFileCrocKeepsItsOwnCode(): void
    {
        // One widely-copied table puts FileCroc on 'FL'; libtorrent,
        // Transmission and BEP 20 all put it on 'FC', where it stays.
        $this->assertSame('FileCroc 0.1.0.2', stats_client_detect($this->hex('-FC0102-abcdefghijkl')));
    }

    public function testFolxIsIdentifiedDespiteItsShortPeerId(): void
    {
        // Folx encodes its version as one base-62 character, so the id is a
        // byte short of the '-XX####-' shape and never reaches the Azureus
        // branch. Without the prefix fallback, half a tracker's Folx peers read
        // as 'Unknown' while the other half read as 'Folx'.
        $this->assertSame('Folx', stats_client_detect($this->hex('-FL5Y0-abcdefghijklm')));
    }

    public function testPrefixFallbackDoesNotShadowTheAzureusTable(): void
    {
        // The fallback runs last, so it can only name a peer_id nothing else
        // claimed — a well-formed code still wins, version and all.
        $this->assertSame('Transmission 4.1.3.0', stats_client_detect($this->hex('-TR4130-abcdefghijkl')));
        $this->assertSame('ZZ 0.0.0.0', stats_client_detect($this->hex('-ZZ0000-abcdefghijkl')));
        $this->assertSame('Unknown', stats_client_detect(str_repeat('7a', 20)));
    }

    public function testIdentifiesCodesCorroboratedByTwoImplementations(): void
    {
        // Absent from BEP 20, but each is named identically by at least two of
        // libtorrent, Transmission and bittorrent-peerid. FlashGet in
        // particular settles an old question: it is FG, never FL.
        $this->assertSame('FlashGet 1.0.0.0', stats_client_detect($this->hex('-FG1000-aaaaaaaaaaaa')));
        $this->assertSame('GetRight 1.0.0.0', stats_client_detect($this->hex('-GR1000-aaaaaaaaaaaa')));
        $this->assertSame('µTorrent Embedded 1.0.0.0', stats_client_detect($this->hex('-UE1000-aaaaaaaaaaaa')));
        // A lowercase code is a distinct code, not a case variant.
        $this->assertSame('pHoeniX 1.0.0.0', stats_client_detect($this->hex('-pX1000-aaaaaaaaaaaa')));
        $this->assertSame('BitKitten (libtorrent) 1.0.0.0', stats_client_detect($this->hex('-bk1000-aaaaaaaaaaaa')));
    }

    public function testSingleSourcedCodesAreDeliberatelyAbsent(): void
    {
        // bittorrent-peerid lists these and nothing corroborates them. It is
        // also the table that puts FileCroc on FL, where three other sources
        // put it on FC — so one source alone does not earn an entry, and a
        // bare code is the honest answer until something else agrees.
        foreach (['AN', 'CB', 'PC', 'PE', 'RM', 'SG', 'TG'] as $code) {
            $this->assertSame(
                $code.' 1.0.0.0',
                stats_client_detect($this->hex('-'.$code.'1000-aaaaaaaaaaaa')),
                $code.' must stay a bare code',
            );
        }
    }
}
