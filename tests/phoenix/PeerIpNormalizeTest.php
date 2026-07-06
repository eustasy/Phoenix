<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../src/functions/peer.ip.normalize.php';

class PeerIpNormalizeTest extends TestCase
{
    public function testPlainIpv4(): void
    {
        $this->assertSame('203.0.113.5', peer_ip_normalize('203.0.113.5'));
    }

    public function testIpv4WithPortIsStripped(): void
    {
        $this->assertSame('203.0.113.5', peer_ip_normalize('203.0.113.5:8080'));
    }

    public function testBareIpv6IsNotPortStripped(): void
    {
        $this->assertSame('2001:db8::1', peer_ip_normalize('2001:db8::1'));
        $this->assertSame('::1', peer_ip_normalize('::1'));
    }

    public function testBracketedIpv6(): void
    {
        $this->assertSame('2001:db8::1', peer_ip_normalize('[2001:db8::1]'));
    }

    public function testBracketedIpv6WithPort(): void
    {
        $this->assertSame('2001:db8::1', peer_ip_normalize('[2001:db8::1]:4711'));
    }

    public function testStripsRfc7239Quotes(): void
    {
        $this->assertSame('203.0.113.5', peer_ip_normalize('"203.0.113.5"'));
        $this->assertSame('2001:db8::1', peer_ip_normalize('"[2001:db8::1]:4711"'));
    }

    public function testTrimsWhitespace(): void
    {
        $this->assertSame('203.0.113.5', peer_ip_normalize('  203.0.113.5  '));
    }

    public function testRejectsObfuscatedIdentifiers(): void
    {
        $this->assertNull(peer_ip_normalize('_hidden'));
        $this->assertNull(peer_ip_normalize('unknown'));
    }

    public function testRejectsEmptyAndGarbage(): void
    {
        $this->assertNull(peer_ip_normalize(''));
        $this->assertNull(peer_ip_normalize('   '));
        $this->assertNull(peer_ip_normalize('not-an-ip'));
        $this->assertNull(peer_ip_normalize('example.com'));
    }

    public function testRejectsUnterminatedBracket(): void
    {
        $this->assertNull(peer_ip_normalize('[2001:db8::1'));
    }
}
