<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../src/functions/ip.in.cidr.php';

class IpInCidrTest extends TestCase
{
    public function testIpv4WithinRange(): void
    {
        $this->assertTrue(ip_in_cidr('10.5.6.7', '10.0.0.0/8'));
        $this->assertTrue(ip_in_cidr('192.168.1.50', '192.168.0.0/16'));
        $this->assertTrue(ip_in_cidr('203.0.113.9', '203.0.113.0/24'));
    }

    public function testIpv4OutsideRange(): void
    {
        $this->assertFalse(ip_in_cidr('11.0.0.1', '10.0.0.0/8'));
        $this->assertFalse(ip_in_cidr('203.0.114.1', '203.0.113.0/24'));
    }

    public function testNonByteBoundaryPrefix(): void
    {
        // /28 leaves 4 host bits: .0–.15 in range, .16 out.
        $this->assertTrue(ip_in_cidr('192.0.2.15', '192.0.2.0/28'));
        $this->assertFalse(ip_in_cidr('192.0.2.16', '192.0.2.0/28'));
    }

    public function testBareAddressIsExactMatch(): void
    {
        $this->assertTrue(ip_in_cidr('10.0.0.1', '10.0.0.1'));
        $this->assertFalse(ip_in_cidr('10.0.0.2', '10.0.0.1'));
    }

    public function testZeroPrefixMatchesAnySameFamily(): void
    {
        $this->assertTrue(ip_in_cidr('8.8.8.8', '0.0.0.0/0'));
    }

    public function testIpv6WithinAndOutsideRange(): void
    {
        $this->assertTrue(ip_in_cidr('2001:db8::1', '2001:db8::/32'));
        $this->assertTrue(ip_in_cidr('fd00::abcd', 'fc00::/7'));
        $this->assertFalse(ip_in_cidr('2001:dead::1', '2001:db8::/32'));
    }

    public function testMismatchedFamilyNeverMatches(): void
    {
        $this->assertFalse(ip_in_cidr('10.0.0.1', '2001:db8::/32'));
        $this->assertFalse(ip_in_cidr('2001:db8::1', '10.0.0.0/8'));
    }

    public function testMalformedInputReturnsFalse(): void
    {
        $this->assertFalse(ip_in_cidr('not-an-ip', '10.0.0.0/8'));
        $this->assertFalse(ip_in_cidr('10.0.0.1', 'garbage/8'));
        $this->assertFalse(ip_in_cidr('10.0.0.1', '10.0.0.0/x'));
        $this->assertFalse(ip_in_cidr('10.0.0.1', '10.0.0.0/99'));
    }
}
