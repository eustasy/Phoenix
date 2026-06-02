<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class ViewAnnounceBencodeTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/bencode.announce.php';
    }

    private function defaultCounts(): array
    {
        return ['complete' => 5, 'incomplete' => 3];
    }

    private function defaultSettings(): array
    {
        return [
            'announce_interval' => 1800,
            'min_interval' => 900,
        ];
    }

    public function testBasicStructureWithEmptyPeers(): void
    {
        $result = view_announce_bencode(
            $this->defaultCounts(),
            $this->defaultSettings(),
            [], // no peers
            false,   // not compact
            false,    // include peer_id
        );

        $this->assertStringStartsWith('d8:completei5e', $result);
        $this->assertStringContainsString('10:incompletei3e', $result);
        $this->assertStringContainsString('8:intervali1800e', $result);
        $this->assertStringContainsString('12:min intervali900e', $result);
        $this->assertStringContainsString('5:peers', $result);
        $this->assertStringEndsWith('e', $result);
    }

    public function testKeysInLexicographicOrder(): void
    {
        $result = view_announce_bencode(
            $this->defaultCounts(),
            $this->defaultSettings(),
            [],
            false,
            false,
        );

        // Keys must appear in order: complete, incomplete, interval, min interval, peers
        $completePos = strpos($result, '8:complete');
        $incompletePos = strpos($result, '10:incomplete');
        $intervalPos = strpos($result, '8:interval');
        $minIntervalPos = strpos($result, '12:min interval');
        $peersPos = strpos($result, '5:peers');

        $this->assertLessThan($incompletePos, $completePos);
        $this->assertLessThan($intervalPos, $incompletePos);
        $this->assertLessThan($minIntervalPos, $intervalPos);
        $this->assertLessThan($peersPos, $minIntervalPos);
    }

    public function testNonCompactModeWithIPv4Peer(): void
    {
        $rows = [
            [
                'ipv4' => '192.168.1.1',
                'portv4' => 6881,
                'ipv6' => null,
                'portv6' => null,
                'peer_id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            ],
        ];

        $result = view_announce_bencode(
            $this->defaultCounts(),
            $this->defaultSettings(),
            $rows,
            false, // not compact
            false,  // include peer_id
        );

        // Non-compact peers are a list
        $this->assertStringContainsString('5:peersl', $result);
        // Peer dict contains IP
        $this->assertStringContainsString('2:ip11:192.168.1.1', $result);
        // Peer dict contains port
        $this->assertStringContainsString('4:porti6881e', $result);
        // Peer dict contains peer_id (20 raw bytes)
        $this->assertStringContainsString('7:peer id20:', $result);
    }

    public function testNonCompactModeWithIPv6Peer(): void
    {
        $rows = [
            [
                'ipv4' => null,
                'portv4' => null,
                'ipv6' => '2001:db8::1',
                'portv6' => 6882,
                'peer_id' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            ],
        ];

        $result = view_announce_bencode(
            $this->defaultCounts(),
            $this->defaultSettings(),
            $rows,
            false,
            false,
        );

        $this->assertStringContainsString('2:ip11:2001:db8::1', $result);
        $this->assertStringContainsString('4:porti6882e', $result);
    }

    public function testNonCompactModeOmitsPeerIdWhenRequested(): void
    {
        $rows = [
            [
                'ipv4' => '10.0.0.1',
                'portv4' => 6883,
                'ipv6' => null,
                'portv6' => null,
                'peer_id' => 'cccccccccccccccccccccccccccccccccccccccc',
            ],
        ];

        $result = view_announce_bencode(
            $this->defaultCounts(),
            $this->defaultSettings(),
            $rows,
            false,
            true,  // no_peer_id = true
        );

        $this->assertStringContainsString('2:ip8:10.0.0.1', $result);
        $this->assertStringContainsString('4:porti6883e', $result);
        // Should NOT contain peer_id
        $this->assertStringNotContainsString('7:peer id', $result);
    }

    public function testCompactModeWithIPv4Peers(): void
    {
        // Compact IPv4: 4 bytes IP + 2 bytes port = 6 bytes total
        $rows = [
            [
                'compactv4' => 'c0a80101aabb', // 192.168.1.1:43707 in hex
                'compactv6' => null,
            ],
            [
                'compactv4' => 'c0a80102ccdd', // 192.168.1.2:52445 in hex
                'compactv6' => null,
            ],
        ];

        $result = view_announce_bencode(
            $this->defaultCounts(),
            $this->defaultSettings(),
            $rows,
            true,  // compact mode
            false,
        );

        // Compact mode: peers is a binary string
        $this->assertStringContainsString('5:peers12:', $result); // length 12 (2 peers × 6 bytes)
        // The binary data follows
        $this->assertStringContainsString(hex2bin('c0a80101aabb'), $result);
        $this->assertStringContainsString(hex2bin('c0a80102ccdd'), $result);
    }

    public function testCompactModeWithIPv6Peers(): void
    {
        // Compact IPv6: 16 bytes IP + 2 bytes port = 18 bytes total
        $rows = [
            [
                'compactv4' => null,
                'compactv6' => '20010db8000000000000000000000001aabb', // 2001:db8::1:43707
            ],
        ];

        $result = view_announce_bencode(
            $this->defaultCounts(),
            $this->defaultSettings(),
            $rows,
            true,
            false,
        );

        // IPv6 compact goes in 'peers6' key
        $this->assertStringContainsString('6:peers6', $result);
        $this->assertStringContainsString('18:', $result); // length 18 (1 peer × 18 bytes)
        $this->assertStringContainsString(hex2bin('20010db8000000000000000000000001aabb'), $result);
    }

    public function testCompactModeWithMixedPeers(): void
    {
        $rows = [
            [
                'compactv4' => 'c0a80101aabb',
                'compactv6' => null,
            ],
            [
                'compactv4' => null,
                'compactv6' => '20010db8000000000000000000000001ccdd',
            ],
        ];

        $result = view_announce_bencode(
            $this->defaultCounts(),
            $this->defaultSettings(),
            $rows,
            true,
            false,
        );

        // Should have both peers and peers6
        $this->assertStringContainsString('5:peers6:', $result); // IPv4 (6 bytes)
        $this->assertStringContainsString('6:peers618:', $result); // IPv6 (18 bytes)
    }

    public function testCompactModeWithNoPeers(): void
    {
        $result = view_announce_bencode(
            $this->defaultCounts(),
            $this->defaultSettings(),
            [], // no peers
            true,    // compact mode
            false,
        );

        // Empty compact peers are 0-length strings
        $this->assertStringContainsString('5:peers0:', $result);
        $this->assertStringContainsString('6:peers60:', $result);
    }

    public function testMultiplePeersInNonCompactMode(): void
    {
        $rows = [
            [
                'ipv4' => '10.0.0.1',
                'portv4' => 6881,
                'ipv6' => null,
                'portv6' => null,
                'peer_id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            ],
            [
                'ipv4' => '10.0.0.2',
                'portv4' => 6882,
                'ipv6' => null,
                'portv6' => null,
                'peer_id' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            ],
        ];

        $result = view_announce_bencode(
            $this->defaultCounts(),
            $this->defaultSettings(),
            $rows,
            false,
            false,
        );

        // Should have both peer dicts in the list
        $this->assertStringContainsString('2:ip8:10.0.0.1', $result);
        $this->assertStringContainsString('2:ip8:10.0.0.2', $result);
        $this->assertStringContainsString('4:porti6881e', $result);
        $this->assertStringContainsString('4:porti6882e', $result);
    }

}
