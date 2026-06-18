<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/controller/scrape.specific.php';

class ScrapeSpecificControllerTest extends PhoenixTestCase
{
    // 40-char hex info_hashes (view_scrape_bencode hex2bin's them).
    private const HASH_A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const HASH_B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private int $errorReporting;

    /** @var array<string, mixed> */
    private array $getBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->errorReporting = error_reporting();
        $this->getBackup = $_GET;
        error_reporting(0);

        // Two known torrents: A has data + 2 seeders + 1 leecher,
        // B is registered but has no peers, exercises the zero-init path.
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
            '(`info_hash`, `name`, `size`, `listed`, `downloads`) VALUES '.
            '(\''.self::HASH_A.'\', \'A\', 1024, 1, 7), '.
            '(\''.self::HASH_B.'\', \'B\',    0, 1, 0);',
        );
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
            '(`info_hash`, `peer_id`, `compactv4`, `compactv6`, `portv4`, `portv6`, `state`, `updated`) VALUES '.
            '(\''.self::HASH_A.'\', \'__TEST_specific_seed_1__\', \'\', \'\', 0, 0, 1, '.self::$time.'), '.
            '(\''.self::HASH_A.'\', \'__TEST_specific_seed_2__\', \'\', \'\', 0, 0, 1, '.self::$time.'), '.
            '(\''.self::HASH_A.'\', \'__TEST_specific_leech__\', \'\', \'\', 0, 0, 0, '.self::$time.');',
        );
    }

    protected function tearDown(): void
    {
        error_reporting($this->errorReporting);
        $_GET = $this->getBackup;
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` IN '.
            '(\''.self::HASH_A.'\', \''.self::HASH_B.'\');',
        );
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'peers` WHERE `info_hash` IN '.
            '(\''.self::HASH_A.'\', \''.self::HASH_B.'\');',
        );
        parent::tearDown();
    }

    // Expected per-torrent rendering. HASH_A: size=1024, downloads=7, 2
    // seeders, 1 leecher → peers=3, traffic=size*downloads=7168.
    // HASH_B: zero-initialised by scrape_initialize_results because no
    // peers exist for it; size & downloads come from the torrents row.

    private const HASH_A_BENCODE = 'd8:completei2e10:downloadedi7e10:incompletei1ee';
    private const HASH_A_XML =
        '<torrent>'.
        '<info_hash>aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa</info_hash>'.
        '<seeders>2</seeders>'.
        '<leechers>1</leechers>'.
        '<peers>3</peers>'.
        '<size>1024</size>'.
        '<downloads>7</downloads>'.
        '<traffic>7168</traffic>'.
        '</torrent>';
    private const HASH_A_JSON = [
        'info_hash' => self::HASH_A,
        'seeders' => 2,
        'leechers' => 1,
        'peers' => 3,
        'size' => 1024,
        'downloads' => 7,
        'traffic' => 7168,
    ];

    private const HASH_B_BENCODE = 'd8:completei0e10:downloadedi0e10:incompletei0ee';
    private const HASH_B_XML =
        '<torrent>'.
        '<info_hash>bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb</info_hash>'.
        '<seeders>0</seeders>'.
        '<leechers>0</leechers>'.
        '<peers>0</peers>'.
        '<size>0</size>'.
        '<downloads>0</downloads>'.
        '<traffic>0</traffic>'.
        '</torrent>';
    private const HASH_B_JSON = [
        'info_hash' => self::HASH_B,
        'seeders' => 0,
        'leechers' => 0,
        'peers' => 0,
        'size' => 0,
        'downloads' => 0,
        'traffic' => 0,
    ];

    /**
     * BEP 48 specifies exactly three keys per torrent dict (complete,
     * downloaded, incomplete). Phoenix's XML/JSON renders extend that with
     * peers/size/traffic for caller convenience, but the bencode output
     * must stay strictly conformant — strict BitTorrent clients are within
     * their rights to reject responses that carry unknown keys.
     */
    private function assertNoNonStandardBencodeFields(string $bencode): void
    {
        // Bencode keys are encoded as <length>:<key>. Each non-standard
        // field has a unique length prefix that won't collide with the
        // 5:files outer key or the standard 8:complete / 10:downloaded /
        // 10:incomplete inner keys.
        $this->assertStringNotContainsString('9:info_hash', $bencode);
        $this->assertStringNotContainsString('5:peers', $bencode);
        $this->assertStringNotContainsString('4:size', $bencode);
        $this->assertStringNotContainsString('7:traffic', $bencode);
    }

    public function testRendersBencodeForSingleHash(): void
    {
        $_GET = [];
        $bencode = \scrape_specific_controller(self::$connection, self::$settings, [self::HASH_A]);

        $this->assertStringStartsWith('d5:files', $bencode);
        $this->assertStringEndsWith('ee', $bencode);
        $this->assertStringContainsString(
            '20:'.hex2bin(self::HASH_A).self::HASH_A_BENCODE,
            $bencode,
        );
        $this->assertNoNonStandardBencodeFields($bencode);
    }

    public function testRendersBencodeForMultiHash(): void
    {
        $_GET = [];
        $bencode = \scrape_specific_controller(
            self::$connection,
            self::$settings,
            [self::HASH_A, self::HASH_B],
        );

        $this->assertStringStartsWith('d5:files', $bencode);
        $this->assertStringEndsWith('ee', $bencode);
        $this->assertStringContainsString(
            '20:'.hex2bin(self::HASH_A).self::HASH_A_BENCODE,
            $bencode,
        );
        $this->assertStringContainsString(
            '20:'.hex2bin(self::HASH_B).self::HASH_B_BENCODE,
            $bencode,
        );
        $this->assertNoNonStandardBencodeFields($bencode);
    }

    public function testRendersXmlForSingleHash(): void
    {
        $_GET = ['xml' => '1'];
        $xml = \scrape_specific_controller(self::$connection, self::$settings, [self::HASH_A]);

        $this->assertStringStartsWith('<?xml', $xml);
        $this->assertStringContainsString('<scrape>', $xml);
        $this->assertStringEndsWith('</scrape>', $xml);
        $this->assertStringContainsString(self::HASH_A_XML, $xml);
    }

    public function testRendersXmlForMultiHash(): void
    {
        $_GET = ['xml' => '1'];
        $xml = \scrape_specific_controller(
            self::$connection,
            self::$settings,
            [self::HASH_A, self::HASH_B],
        );

        $this->assertStringContainsString(self::HASH_A_XML, $xml);
        $this->assertStringContainsString(self::HASH_B_XML, $xml);
    }

    public function testRendersJsonForSingleHash(): void
    {
        $_GET = ['json' => '1'];
        $json = \scrape_specific_controller(self::$connection, self::$settings, [self::HASH_A]);

        // Pin the entire decoded payload — single-hash responses contain
        // exactly one torrent entry plus the top-level BEP 48 throttle hint,
        // so equality is the right shape here.
        $this->assertSame(
            [
                self::HASH_A => self::HASH_A_JSON,
                'min_request_interval' => self::$settings['scrape_min_interval'],
            ],
            json_decode($json, true),
        );
    }

    public function testRendersJsonForMultiHash(): void
    {
        $_GET = ['json' => '1'];
        $json = \scrape_specific_controller(
            self::$connection,
            self::$settings,
            [self::HASH_A, self::HASH_B],
        );
        $decoded = json_decode($json, true);

        // HASH_B is zero-initialised by scrape_initialize_results because
        // no peers exist for it — same path as the previous
        // testZeroInitsRequestedHashWithNoPeers, now rolled into the
        // fields-completeness check.
        $this->assertSame(self::HASH_A_JSON, $decoded[self::HASH_A]);
        $this->assertSame(self::HASH_B_JSON, $decoded[self::HASH_B]);
    }

}
