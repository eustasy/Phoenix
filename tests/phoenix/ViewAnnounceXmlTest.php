<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class ViewAnnounceXmlTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/xml.announce.php';
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
        $result = view_announce_xml(
            $this->defaultCounts(),
            $this->defaultSettings(),
            [], // no peers
        );

        $this->assertIsString($result);
        $this->assertStringStartsWith('<?xml version="1.0"', $result);
        $this->assertStringContainsString('<announce>', $result);
        $this->assertStringContainsString('<complete>5</complete>', $result);
        $this->assertStringContainsString('<incomplete>3</incomplete>', $result);
        $this->assertStringContainsString('<interval>1800</interval>', $result);
        $this->assertStringContainsString('<min_interval>900</min_interval>', $result);
        $this->assertStringContainsString('<peers>', $result);
        $this->assertStringContainsString('</peers>', $result);
        $this->assertStringContainsString('</announce>', $result);
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

        $result = view_announce_xml(
            $this->defaultCounts(),
            $this->defaultSettings(),
            $rows,
        );

        $this->assertStringContainsString('<peer>', $result);
        $this->assertStringContainsString('<ip>192.168.1.1</ip>', $result);
        $this->assertStringContainsString('<port>6881</port>', $result);
        $this->assertStringContainsString('<peer_id>aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa</peer_id>', $result);
        $this->assertStringContainsString('</peer>', $result);
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

        $result = view_announce_xml(
            $this->defaultCounts(),
            $this->defaultSettings(),
            $rows,
        );

        $this->assertStringContainsString('<peer>', $result);
        $this->assertStringContainsString('<ip>2001:db8::1</ip>', $result);
        $this->assertStringContainsString('<port>6882</port>', $result);
        $this->assertStringContainsString('<peer_id>bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb</peer_id>', $result);
        $this->assertStringContainsString('</peer>', $result);
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
        ];

        $result = view_announce_xml(
            $this->defaultCounts(),
            $this->defaultSettings(),
            $rows,
        );

        // Count occurrences of <peer> tags
        $peerCount = substr_count($result, '<peer>');
        $this->assertSame(2, $peerCount);

        // Verify both peers are in output
        $this->assertStringContainsString('<ip>192.168.1.1</ip>', $result);
        $this->assertStringContainsString('<port>6881</port>', $result);
        $this->assertStringContainsString('<ip>2001:db8::1</ip>', $result);
        $this->assertStringContainsString('<port>6882</port>', $result);
    }

    public function testValidXml(): void
    {
        $result = view_announce_xml(
            $this->defaultCounts(),
            $this->defaultSettings(),
            [],
        );

        // Ensure result is valid XML
        $prev = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($result);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $this->assertNotFalse($xml, 'XML should be valid');
        $this->assertEmpty($errors, 'XML should have no parsing errors');
    }

    public function testXmlStructure(): void
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

        $result = view_announce_xml(
            $this->defaultCounts(),
            $this->defaultSettings(),
            $rows,
        );

        $xml = simplexml_load_string($result);
        $this->assertNotFalse($xml);

        // Verify root element
        $this->assertSame('announce', $xml->getName());

        // Verify swarm counts
        $this->assertSame('5', (string)$xml->complete);
        $this->assertSame('3', (string)$xml->incomplete);
        $this->assertSame('1800', (string)$xml->interval);
        $this->assertSame('900', (string)$xml->min_interval);

        // Verify peers structure
        $this->assertCount(1, $xml->peers->peer);
        $peer = $xml->peers->peer[0];
        $this->assertSame('192.168.1.1', (string)$peer->ip);
        $this->assertSame('6881', (string)$peer->port);
        $this->assertSame('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', (string)$peer->peer_id);
    }

    public function testExternalIpElement(): void
    {
        $result = view_announce_xml(
            $this->defaultCounts(),
            $this->defaultSettings(),
            [],
            '203.0.113.5', // BEP 24
        );

        $xml = simplexml_load_string($result);
        $this->assertNotFalse($xml);
        $this->assertSame('203.0.113.5', (string)$xml->external_ip);
    }

    public function testExternalIpOmittedByDefault(): void
    {
        $result = view_announce_xml(
            $this->defaultCounts(),
            $this->defaultSettings(),
            [],
        );

        $this->assertStringNotContainsString('<external_ip>', $result);
    }

}
