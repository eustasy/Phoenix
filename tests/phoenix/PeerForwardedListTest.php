<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../src/functions/peer.forwarded.list.php';

class PeerForwardedListTest extends TestCase
{
    public function testXffChainInOrder(): void
    {
        $this->assertSame(
            ['203.0.113.5', '198.51.100.7', '10.0.0.1'],
            peer_forwarded_list('203.0.113.5, 198.51.100.7, 10.0.0.1', false),
        );
    }

    public function testXffTrimsAndDropsBlanks(): void
    {
        $this->assertSame(
            ['203.0.113.5', '198.51.100.7'],
            peer_forwarded_list('  203.0.113.5 , , 198.51.100.7 ', false),
        );
    }

    public function testXffMixedFamilies(): void
    {
        $this->assertSame(
            ['2001:db8::1', '203.0.113.5'],
            peer_forwarded_list('2001:db8::1, 203.0.113.5', false),
        );
    }

    public function testXffEmptyAndAllBlank(): void
    {
        $this->assertSame([], peer_forwarded_list('', false));
        $this->assertSame([], peer_forwarded_list(' , , ', false));
    }

    public function testRfc7239ForValues(): void
    {
        $this->assertSame(
            ['203.0.113.5', '198.51.100.7'],
            peer_forwarded_list('for=203.0.113.5;proto=https, for=198.51.100.7', true),
        );
    }

    public function testRfc7239BracketedIpv6(): void
    {
        $this->assertSame(
            ['2001:db8::1', '203.0.113.5'],
            peer_forwarded_list('for="[2001:db8::1]:4711", for=203.0.113.5', true),
        );
    }

    public function testRfc7239CaseInsensitiveForKey(): void
    {
        $this->assertSame(['203.0.113.5'], peer_forwarded_list('For=203.0.113.5', true));
    }

    public function testRfc7239SkipsElementsWithoutForAndObfuscated(): void
    {
        $this->assertSame(
            ['203.0.113.5'],
            peer_forwarded_list('proto=https, for=_hidden, for=203.0.113.5', true),
        );
    }
}
