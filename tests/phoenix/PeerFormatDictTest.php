<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class PeerFormatDictTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/peer.format.dict.php';
    }

    public function testIpv4WithPeerId(): void
    {
        $row = [
            'ipv4' => '1.2.3.4',
            'ipv6' => null,
            'portv4' => 12345,
            'portv6' => 0,
            'peer_id' => str_repeat('00', 20),
        ];
        $this->assertSame(
            [
                'ip' => '1.2.3.4',
                'port' => 12345,
                'peer id' => hex2bin(str_repeat('00', 20)),
            ],
            peer_format_dict($row, true),
        );
    }

    public function testIpv4WithoutPeerId(): void
    {
        $row = [
            'ipv4' => '1.2.3.4',
            'ipv6' => null,
            'portv4' => 12345,
            'portv6' => 0,
            'peer_id' => str_repeat('00', 20),
        ];
        $dict = peer_format_dict($row, false);
        $this->assertSame(['ip' => '1.2.3.4', 'port' => 12345], $dict);
        $this->assertArrayNotHasKey('peer id', $dict);
    }

    public function testIpv6Only(): void
    {
        $row = [
            'ipv4' => null,
            'ipv6' => 'dead::1',
            'portv4' => 0,
            'portv6' => 12345,
            'peer_id' => str_repeat('ff', 20),
        ];
        $this->assertSame(
            ['ip' => 'dead::1', 'port' => 12345],
            peer_format_dict($row, false),
        );
    }

    public function testIpv4TakesPrecedenceWhenBothSet(): void
    {
        $row = [
            'ipv4' => '1.2.3.4',
            'ipv6' => 'dead::1',
            'portv4' => 12345,
            'portv6' => 54321,
            'peer_id' => str_repeat('00', 20),
        ];
        $this->assertSame(
            ['ip' => '1.2.3.4', 'port' => 12345],
            peer_format_dict($row, false),
        );
    }

    public function testReturnsNullWhenNoAddress(): void
    {
        // A row with neither family is skipped by the caller rather than
        // emitting an empty peer dict.
        $row = [
            'ipv4' => null,
            'ipv6' => null,
            'portv4' => 0,
            'portv6' => 0,
            'peer_id' => str_repeat('00', 20),
        ];
        $this->assertNull(peer_format_dict($row, true));
        $this->assertNull(peer_format_dict($row, false));
    }

    public function testPortCoercedToInt(): void
    {
        // mysqli returns numeric columns as strings; they must bencode as
        // integers, so peer_format_dict casts them here.
        $row = [
            'ipv4' => '1.2.3.4',
            'ipv6' => null,
            'portv4' => '6881',
            'portv6' => '0',
            'peer_id' => str_repeat('aa', 20),
        ];
        $dict = peer_format_dict($row, false);
        $this->assertSame(6881, $dict['port']);
    }

}
