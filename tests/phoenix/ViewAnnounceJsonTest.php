<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class ViewAnnounceJsonTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/json.announce.php';
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
        $result = view_announce_json(
            $this->defaultCounts(),
            $this->defaultSettings(),
            [], // no peers
        );

        $this->assertIsString($result);
        $data = json_decode($result, true);
        $this->assertNotNull($data);
        $this->assertIsArray($data);

        $this->assertArrayHasKey('complete', $data);
        $this->assertArrayHasKey('incomplete', $data);
        $this->assertArrayHasKey('interval', $data);
        $this->assertArrayHasKey('min_interval', $data);
        $this->assertArrayHasKey('peers', $data);

        $this->assertSame(5, $data['complete']);
        $this->assertSame(3, $data['incomplete']);
        $this->assertSame(1800, $data['interval']);
        $this->assertSame(900, $data['min_interval']);
        $this->assertSame([], $data['peers']);
    }

    public function testWithIPv4Peer(): void
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

        $result = view_announce_json(
            $this->defaultCounts(),
            $this->defaultSettings(),
            $rows,
        );

        $data = json_decode($result, true);
        $this->assertCount(1, $data['peers']);

        $peer = $data['peers'][0];
        $this->assertArrayHasKey('peer_id', $peer);
        $this->assertArrayHasKey('ip', $peer);
        $this->assertArrayHasKey('port', $peer);

        $this->assertSame('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $peer['peer_id']);
        $this->assertSame('192.168.1.1', $peer['ip']);
        $this->assertSame(6881, $peer['port']);
    }

    public function testWithIPv6Peer(): void
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

        $result = view_announce_json(
            $this->defaultCounts(),
            $this->defaultSettings(),
            $rows,
        );

        $data = json_decode($result, true);
        $this->assertCount(1, $data['peers']);

        $peer = $data['peers'][0];
        $this->assertSame('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', $peer['peer_id']);
        $this->assertSame('2001:db8::1', $peer['ip']);
        $this->assertSame(6882, $peer['port']);
    }

    public function testWithMultiplePeers(): void
    {
        $rows = [
            [
                'ipv4' => '192.168.1.1',
                'portv4' => 6881,
                'ipv6' => null,
                'portv6' => null,
                'peer_id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            ],
            [
                'ipv4' => null,
                'portv4' => null,
                'ipv6' => '2001:db8::1',
                'portv6' => 6882,
                'peer_id' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            ],
            [
                'ipv4' => '10.0.0.1',
                'portv4' => 6883,
                'ipv6' => null,
                'portv6' => null,
                'peer_id' => 'cccccccccccccccccccccccccccccccccccccccc',
            ],
        ];

        $result = view_announce_json(
            $this->defaultCounts(),
            $this->defaultSettings(),
            $rows,
        );

        $data = json_decode($result, true);
        $this->assertCount(3, $data['peers']);

        // Verify first peer
        $this->assertSame('192.168.1.1', $data['peers'][0]['ip']);
        $this->assertSame(6881, $data['peers'][0]['port']);

        // Verify second peer (IPv6)
        $this->assertSame('2001:db8::1', $data['peers'][1]['ip']);
        $this->assertSame(6882, $data['peers'][1]['port']);

        // Verify third peer
        $this->assertSame('10.0.0.1', $data['peers'][2]['ip']);
        $this->assertSame(6883, $data['peers'][2]['port']);
    }

    public function testValidJson(): void
    {
        $result = view_announce_json(
            $this->defaultCounts(),
            $this->defaultSettings(),
            [],
        );

        // Ensure result is valid JSON
        json_decode($result);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
    }

    public function testExternalIpField(): void
    {
        $result = view_announce_json(
            $this->defaultCounts(),
            $this->defaultSettings(),
            [],
            '203.0.113.5', // BEP 24
        );

        $data = json_decode($result, true);
        $this->assertArrayHasKey('external_ip', $data);
        $this->assertSame('203.0.113.5', $data['external_ip']);
    }

    public function testExternalIpOmittedByDefault(): void
    {
        $result = view_announce_json(
            $this->defaultCounts(),
            $this->defaultSettings(),
            [],
        );

        $data = json_decode($result, true);
        $this->assertArrayNotHasKey('external_ip', $data);
    }

}
