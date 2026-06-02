<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class PeerInsertTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/model/peer.insert.php';
    }

    protected function tearDown(): void
    {
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'peers` WHERE `info_hash` LIKE \'__TEST_%\';',
        );
    }

    /**
     * Build a peer row with the IP/port fields the test wants and zeroed
     * counters for everything else.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function fixturePeer(array $overrides): array
    {
        return array_replace([
            'info_hash' => '__TEST_PI__',
            'peer_id' => '__TEST_PI__',
            'state' => 0,
            'left' => 0,
            'uploaded' => 0,
            'downloaded' => 0,
            'ipv4' => '',
            'ipv6' => '',
            'portv4' => '0',
            'portv6' => '0',
        ], $overrides);
    }

    /** @return array<string, mixed>|null */
    private function fetchPeer(string $infoHash, string $peerId): ?array
    {
        $result = mysqli_query(
            self::$connection,
            'SELECT `compactv4`, `compactv6`, `ipv4`, `ipv6`, `portv4`, `portv6` '.
            'FROM `'.self::$settings['db_prefix'].'peers` '.
            'WHERE `info_hash` = \''.$infoHash.'\' AND `peer_id` = \''.$peerId.'\';',
        );
        if (! $result) {
            return null;
        }
        $row = mysqli_fetch_assoc($result);

        return $row ?: null;
    }

    public function testEmptyAddressesLeaveCompactColumnsBlank(): void
    {
        // No IPv4 or IPv6 supplied: both compact blocks should short-circuit
        // and the columns should land empty in the DB.
        $peer = $this->fixturePeer([
            'info_hash' => '__TEST_PI_EMPTY__',
            'peer_id' => '__TEST_PI_EMPTY__',
        ]);
        $this->assertTrue(peer_insert(self::$connection, self::$settings, self::$time, $peer));

        $row = $this->fetchPeer('__TEST_PI_EMPTY__', '__TEST_PI_EMPTY__');
        $this->assertNotNull($row);
        $this->assertSame('', $row['compactv4']);
        $this->assertSame('', $row['compactv6']);
    }

    public function testIpv4OnlyEncodesCompactv4PerBep23(): void
    {
        // BEP 23: 4-byte big-endian IP + 2-byte big-endian port = 6 bytes,
        // stored hex = 12 chars. 192.0.2.1:6881 → 0xc00002011ae1.
        $peer = $this->fixturePeer([
            'info_hash' => '__TEST_PI_V4__',
            'peer_id' => '__TEST_PI_V4__',
            'ipv4' => '192.0.2.1',
            'portv4' => '6881',
        ]);
        $this->assertTrue(peer_insert(self::$connection, self::$settings, self::$time, $peer));

        $row = $this->fetchPeer('__TEST_PI_V4__', '__TEST_PI_V4__');
        $this->assertNotNull($row);
        $this->assertSame('c00002011ae1', $row['compactv4']);
        $this->assertSame('', $row['compactv6']);
        $this->assertSame('192.0.2.1', $row['ipv4']);
    }

    public function testIpv6OnlyEncodesCompactv6PerBep7(): void
    {
        // BEP 7: 16-byte address + 2-byte big-endian port = 18 bytes,
        // stored hex = 36 chars. 2001:db8::1:6881 → 16 bytes of address
        // followed by 0x1ae1.
        $peer = $this->fixturePeer([
            'info_hash' => '__TEST_PI_V6__',
            'peer_id' => '__TEST_PI_V6__',
            'ipv6' => '2001:db8::1',
            'portv6' => '6881',
        ]);
        $this->assertTrue(peer_insert(self::$connection, self::$settings, self::$time, $peer));

        $row = $this->fetchPeer('__TEST_PI_V6__', '__TEST_PI_V6__');
        $this->assertNotNull($row);
        $this->assertSame('', $row['compactv4']);
        $this->assertSame('20010db80000000000000000000000011ae1', $row['compactv6']);
        $this->assertSame('2001:db8::1', $row['ipv6']);
    }

    public function testDualStackEncodesBothCompactColumns(): void
    {
        // Both blocks should fire when the peer reports both families.
        $peer = $this->fixturePeer([
            'info_hash' => '__TEST_PI_DUAL__',
            'peer_id' => '__TEST_PI_DUAL__',
            'ipv4' => '192.0.2.2',
            'portv4' => '6882',
            'ipv6' => '2001:db8::2',
            'portv6' => '6883',
        ]);
        $this->assertTrue(peer_insert(self::$connection, self::$settings, self::$time, $peer));

        $row = $this->fetchPeer('__TEST_PI_DUAL__', '__TEST_PI_DUAL__');
        $this->assertNotNull($row);
        $this->assertSame(bin2hex(pack('Nn', ip2long('192.0.2.2'), 6882)), $row['compactv4']);
        $this->assertSame(bin2hex(inet_pton('2001:db8::2').pack('n', 6883)), $row['compactv6']);
    }

    public function testReplaceUpdatesExistingRow(): void
    {
        // REPLACE INTO is the whole point of peer_insert; second call with
        // the same (info_hash, peer_id) overwrites compactv4 with the new
        // IP/port, doesn't error or create a duplicate row.
        $peer = $this->fixturePeer([
            'info_hash' => '__TEST_PI_REPL__',
            'peer_id' => '__TEST_PI_REPL__',
            'ipv4' => '192.0.2.3',
            'portv4' => '6881',
        ]);
        peer_insert(self::$connection, self::$settings, self::$time, $peer);

        $peer['ipv4'] = '192.0.2.4';
        $peer['portv4'] = '6882';
        $this->assertTrue(peer_insert(self::$connection, self::$settings, self::$time, $peer));

        $row = $this->fetchPeer('__TEST_PI_REPL__', '__TEST_PI_REPL__');
        $this->assertNotNull($row);
        $this->assertSame(bin2hex(pack('Nn', ip2long('192.0.2.4'), 6882)), $row['compactv4']);
        $this->assertSame('192.0.2.4', $row['ipv4']);
    }

}
