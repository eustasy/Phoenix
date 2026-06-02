<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class ParseIpv6Test extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/parse.ipv6.php';
    }

    public function testPlainIpv6(): void
    {
        $r = parse_ipv6('dead:beef::1234');
        $this->assertIsArray($r);
        $this->assertSame('dead:beef::1234', $r['ip']);
        $this->assertFalse($r['port']);
    }

    public function testBracketedWithPort(): void
    {
        $r = parse_ipv6('[dead:beef::1234]:12345');
        $this->assertIsArray($r);
        $this->assertSame('dead:beef::1234', $r['ip']);
        $this->assertSame(12345, $r['port']);
    }

    public function testBracketedWithoutPort(): void
    {
        $r = parse_ipv6('[dead:beef::1234]');
        $this->assertIsArray($r);
        $this->assertSame('dead:beef::1234', $r['ip']);
        $this->assertFalse($r['port']);
    }

    public function testRejectsIpv4(): void
    {
        $this->assertFalse(parse_ipv6('101.45.75.219'));
    }

    public function testRejectsGarbage(): void
    {
        $this->assertFalse(parse_ipv6('not an address'));
    }

    public function testRejectsEmpty(): void
    {
        $this->assertFalse(parse_ipv6(''));
    }

    ////	reject_private flag

    public function testUlaAcceptedByDefault(): void
    {
        $r = parse_ipv6('fd00::1');
        $this->assertIsArray($r);
        $this->assertSame('fd00::1', $r['ip']);
    }

    public function testRejectsUlaWhenFlagSet(): void
    {
        // fc00::/7 is the IPv6 unique-local (private) range.
        $this->assertFalse(parse_ipv6('fd00::1', true));
        $this->assertFalse(parse_ipv6('fc00::1', true));
    }

    public function testRejectsReservedWhenFlagSet(): void
    {
        // Loopback and link-local are reserved.
        $this->assertFalse(parse_ipv6('::1', true));
        $this->assertFalse(parse_ipv6('fe80::1', true));
    }

    public function testAcceptsPublicAddressWhenFlagSet(): void
    {
        $r = parse_ipv6('2606:4700:4700::1111', true);
        $this->assertIsArray($r);
        $this->assertSame('2606:4700:4700::1111', $r['ip']);
    }

}
