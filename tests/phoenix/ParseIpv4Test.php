<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class ParseIpv4Test extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/parse.ipv4.php';
    }

    public function testPlainIpv4(): void
    {
        $r = parse_ipv4('101.45.75.219');
        $this->assertIsArray($r);
        $this->assertSame('101.45.75.219', $r['ip']);
        $this->assertFalse($r['port']);
    }

    public function testIpv4WithPort(): void
    {
        $r = parse_ipv4('101.45.75.219:12345');
        $this->assertIsArray($r);
        $this->assertSame('101.45.75.219', $r['ip']);
        $this->assertSame(12345, $r['port']);
    }

    public function testRejectsNonNumericPort(): void
    {
        // Resulting candidate is not a valid IPv4 once the colon-suffix is stripped.
        $this->assertFalse(parse_ipv4('101.45.75.219:abc'));
    }

    public function testRejectsIpv6(): void
    {
        $this->assertFalse(parse_ipv4('dead:beef::1234'));
    }

    public function testRejectsGarbage(): void
    {
        $this->assertFalse(parse_ipv4('not an address'));
    }

    public function testRejectsEmpty(): void
    {
        $this->assertFalse(parse_ipv4(''));
    }

    public function testStripsIpv4MappedIpv6Prefix(): void
    {
        $r = parse_ipv4('::ffff:101.45.75.219');
        $this->assertIsArray($r);
        $this->assertSame('101.45.75.219', $r['ip']);
        $this->assertFalse($r['port']);
    }

    public function testStripsIpv4MappedIpv6PrefixWithPort(): void
    {
        $r = parse_ipv4('::ffff:101.45.75.219:12345');
        $this->assertIsArray($r);
        $this->assertSame('101.45.75.219', $r['ip']);
        $this->assertSame(12345, $r['port']);
    }

    public function testDoesNotStripPrefixCharactersFromAddressProper(): void
    {
        // trim($address, '::ffff:') would have eaten leading/trailing 'f' and ':',
        // breaking parses of plain IPv6 addresses that happen to share those bytes.
        $this->assertFalse(parse_ipv4('ff:ff:ff:ff'));
    }

    ////	reject_private flag

    public function testPrivateRangeAcceptedByDefault(): void
    {
        $r = parse_ipv4('192.168.1.1');
        $this->assertIsArray($r);
        $this->assertSame('192.168.1.1', $r['ip']);
    }

    public function testRejectsPrivateRangeWhenFlagSet(): void
    {
        $this->assertFalse(parse_ipv4('192.168.1.1', true));
        $this->assertFalse(parse_ipv4('10.0.0.1', true));
        $this->assertFalse(parse_ipv4('172.16.0.1', true));
    }

    public function testRejectsReservedRangeWhenFlagSet(): void
    {
        // Loopback and link-local are reserved, not RFC 1918 private.
        $this->assertFalse(parse_ipv4('127.0.0.1', true));
        $this->assertFalse(parse_ipv4('169.254.1.1', true));
    }

    public function testAcceptsPublicAddressWhenFlagSet(): void
    {
        $r = parse_ipv4('8.8.8.8', true);
        $this->assertIsArray($r);
        $this->assertSame('8.8.8.8', $r['ip']);
    }

    public function testKeepsPortWhenRejectingIsRequestedOnPublicAddress(): void
    {
        $r = parse_ipv4('8.8.8.8:6881', true);
        $this->assertIsArray($r);
        $this->assertSame('8.8.8.8', $r['ip']);
        $this->assertSame(6881, $r['port']);
    }

}
