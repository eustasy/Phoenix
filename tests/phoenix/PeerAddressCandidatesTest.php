<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class PeerAddressCandidatesTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/peer.address.candidates.php';
        require_once __DIR__.'/../../src/functions/peer.resolve.addresses.php';
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function settings(array $overrides = []): array
    {
        return array_merge([
            'external_ip' => false,
            'forwarded_headers' => [],
            'trusted_proxies' => [],
            'allow_any_proxy' => false,
        ], $overrides);
    }

    public function testReturnsEmptyWhenNoSourcesAvailable(): void
    {
        $this->assertSame([], peer_address_candidates($this->settings(), [], []));
    }

    public function testReturnsRemoteAddrAlone(): void
    {
        $result = peer_address_candidates($this->settings(), [], ['REMOTE_ADDR' => '10.0.0.1']);
        $this->assertSame(['10.0.0.1'], $result);
    }

    public function testIgnoresGetParamsWhenExternalIpDisabled(): void
    {
        $get = ['ip' => '1.1.1.1', 'ipv4' => '2.2.2.2', 'ipv6' => '::1'];
        $server = ['REMOTE_ADDR' => '10.0.0.1'];
        $this->assertSame(['10.0.0.1'], peer_address_candidates($this->settings(), $get, $server));
    }

    public function testIncludesGetIpsWhenExternalIpEnabled(): void
    {
        $get = ['ip' => '1.1.1.1', 'ipv4' => '2.2.2.2', 'ipv6' => '::1'];
        $server = ['REMOTE_ADDR' => '10.0.0.1'];
        // REMOTE_ADDR outranks the client-supplied values (lowest priority).
        $this->assertSame(
            ['10.0.0.1', '::1', '2.2.2.2', '1.1.1.1'],
            peer_address_candidates($this->settings(['external_ip' => true]), $get, $server),
        );
    }

    public function testIgnoresForwardedHeadersWhenListEmpty(): void
    {
        $server = [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '6.6.6.6',
            'HTTP_CF_CONNECTING_IP' => '7.7.7.7',
        ];
        $this->assertSame(['10.0.0.1'], peer_address_candidates($this->settings(), [], $server));
    }

    ////	Trust gate — empty trusted_proxies fails closed

    public function testEmptyTrustedProxiesFailsClosedByDefault(): void
    {
        // forwarded_headers set but trusted_proxies empty and allow_any_proxy off:
        // the header is NOT trusted, so a direct client cannot spoof its address.
        $settings = $this->settings(['forwarded_headers' => ['x-forwarded-for']]);
        $server = ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6'];
        $this->assertSame(['10.0.0.1'], peer_address_candidates($settings, [], $server));
    }

    public function testEmptyTrustedProxiesHonoredOnlyWithAllowAnyProxy(): void
    {
        $settings = $this->settings(['forwarded_headers' => ['x-forwarded-for'], 'allow_any_proxy' => true]);
        $server = ['REMOTE_ADDR' => '198.51.100.9', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6'];
        $this->assertSame(['6.6.6.6', '198.51.100.9'], peer_address_candidates($settings, [], $server));
    }

    public function testXffHonoredWhenRemoteAddrInTrustedRange(): void
    {
        $settings = $this->settings(['forwarded_headers' => ['x-forwarded-for'], 'trusted_proxies' => ['10.0.0.0/8']]);
        $server = ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.5'];
        $this->assertSame(['203.0.113.5', '10.0.0.1'], peer_address_candidates($settings, [], $server));
    }

    public function testXffIgnoredWhenRemoteAddrNotTrusted(): void
    {
        // A direct (untrusted) connection: X-Forwarded-For is dropped.
        $settings = $this->settings(['forwarded_headers' => ['x-forwarded-for'], 'trusted_proxies' => ['203.0.113.0/24']]);
        $server = ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6'];
        $this->assertSame(['10.0.0.1'], peer_address_candidates($settings, [], $server));
    }

    ////	Rightmost-untrusted-hop walk — the spoof-resistant part

    public function testRightmostWalkSkipsTrustedProxiesAndIgnoresSpoofedLeftmost(): void
    {
        // Chain: [spoofed, real client, internal proxy]. Walking from the right,
        // the internal proxy (trusted) is skipped and the real client returned;
        // the attacker-controlled leftmost entry is never reached.
        $settings = $this->settings(['forwarded_headers' => ['x-forwarded-for'], 'trusted_proxies' => ['10.0.0.0/8']]);
        $server = [
            'REMOTE_ADDR' => '10.0.0.9',
            'HTTP_X_FORWARDED_FOR' => '1.2.3.4, 203.0.113.5, 10.0.0.8',
        ];
        $this->assertSame(['203.0.113.5', '10.0.0.9'], peer_address_candidates($settings, [], $server));
    }

    public function testAppendingProxyCannotBeSpoofed(): void
    {
        // The common nginx `$proxy_add_x_forwarded_for` APPENDS: a client that
        // pre-sends `X-Forwarded-For: 9.9.9.9` yields `9.9.9.9, <real client>`.
        // The rightmost non-trusted entry is the real client; 9.9.9.9 is ignored.
        $settings = $this->settings(['forwarded_headers' => ['x-forwarded-for'], 'trusted_proxies' => ['10.0.0.0/8']]);
        $server = [
            'REMOTE_ADDR' => '10.0.0.9',
            'HTTP_X_FORWARDED_FOR' => '9.9.9.9, 203.0.113.5',
        ];
        $this->assertSame(['203.0.113.5', '10.0.0.9'], peer_address_candidates($settings, [], $server));
    }

    ////	RFC 7239 Forwarded + single-value headers

    public function testForwardedRfc7239Header(): void
    {
        $settings = $this->settings(['forwarded_headers' => ['forwarded'], 'trusted_proxies' => ['10.0.0.0/8']]);
        $server = [
            'REMOTE_ADDR' => '10.0.0.9',
            'HTTP_FORWARDED' => 'for=203.0.113.5;proto=https, for=10.0.0.8',
        ];
        $this->assertSame(['203.0.113.5', '10.0.0.9'], peer_address_candidates($settings, [], $server));
    }

    public function testForwardedRfc7239BracketedIpv6(): void
    {
        $settings = $this->settings(['forwarded_headers' => ['forwarded'], 'trusted_proxies' => ['10.0.0.0/8']]);
        $server = [
            'REMOTE_ADDR' => '10.0.0.9',
            'HTTP_FORWARDED' => 'for="[2001:db8::1]:4711"',
        ];
        $this->assertSame(['2001:db8::1', '10.0.0.9'], peer_address_candidates($settings, [], $server));
    }

    public function testSingleValueHeaderCfConnectingIp(): void
    {
        $settings = $this->settings(['forwarded_headers' => ['cf-connecting-ip'], 'trusted_proxies' => ['10.0.0.0/8']]);
        $server = ['REMOTE_ADDR' => '10.0.0.9', 'HTTP_CF_CONNECTING_IP' => '203.0.113.5'];
        $this->assertSame(['203.0.113.5', '10.0.0.9'], peer_address_candidates($settings, [], $server));
    }

    public function testClientIpStillSelectableWhenListed(): void
    {
        $settings = $this->settings(['forwarded_headers' => ['client-ip'], 'trusted_proxies' => ['10.0.0.0/8']]);
        $server = ['REMOTE_ADDR' => '10.0.0.9', 'HTTP_CLIENT_IP' => '203.0.113.5'];
        $this->assertSame(['203.0.113.5', '10.0.0.9'], peer_address_candidates($settings, [], $server));
    }

    public function testHeaderPriorityFollowsListOrder(): void
    {
        // cf-connecting-ip listed first → highest priority; x-forwarded-for next.
        $settings = $this->settings(['forwarded_headers' => ['cf-connecting-ip', 'x-forwarded-for'], 'trusted_proxies' => ['10.0.0.0/8']]);
        $server = [
            'REMOTE_ADDR' => '10.0.0.9',
            'HTTP_CF_CONNECTING_IP' => '1.1.1.1',
            'HTTP_X_FORWARDED_FOR' => '2.2.2.2',
        ];
        $this->assertSame(['1.1.1.1', '2.2.2.2', '10.0.0.9'], peer_address_candidates($settings, [], $server));
    }

    public function testUnknownForwardedHeaderNameIgnored(): void
    {
        $settings = $this->settings(['forwarded_headers' => ['bogus-header'], 'trusted_proxies' => ['10.0.0.0/8']]);
        $server = ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6'];
        $this->assertSame(['10.0.0.1'], peer_address_candidates($settings, [], $server));
    }

    ////	IPv4 / IPv6 mixing

    public function testIpv6ProxyWithIpv4Client(): void
    {
        // The proxy connects over IPv6 (REMOTE_ADDR + trusted range are v6); the
        // forwarded client is v4. Family-aware CIDR matching handles the mix.
        $settings = $this->settings(['forwarded_headers' => ['x-forwarded-for'], 'trusted_proxies' => ['2001:db8::/32']]);
        $server = ['REMOTE_ADDR' => '2001:db8::9', 'HTTP_X_FORWARDED_FOR' => '203.0.113.5'];
        $this->assertSame(['203.0.113.5', '2001:db8::9'], peer_address_candidates($settings, [], $server));
    }

    public function testMixedFamilyRemoteAddrAndHeaderFillBothSlots(): void
    {
        // A v4 direct peer plus a v6 forwarded address: peer_resolve_addresses
        // must populate BOTH the IPv4 and IPv6 slots from the two sources.
        $settings = $this->settings(['forwarded_headers' => ['x-real-ip'], 'trusted_proxies' => ['198.51.100.0/24']]);
        $server = ['REMOTE_ADDR' => '198.51.100.9', 'HTTP_X_REAL_IP' => '2001:db8::5'];

        $candidates = peer_address_candidates($settings, [], $server);
        $this->assertSame(['2001:db8::5', '198.51.100.9'], $candidates);

        $resolved = peer_resolve_addresses($candidates);
        $this->assertSame('198.51.100.9', $resolved['ipv4']);
        $this->assertSame('2001:db8::5', $resolved['ipv6']);
    }
}
