<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../src/functions/cdn.assets.php';

final class CdnAssetsTest extends TestCase
{
    public function testEveryAssetIsPinnedToAnExactVersion(): void
    {
        // A floating version cannot have a fixed hash, so @latest and a
        // Subresource Integrity attribute are mutually exclusive.
        foreach (cdn_assets() as $key => $asset) {
            $this->assertStringNotContainsString('@latest', $asset['url'], $key.' must be pinned');
            $this->assertMatchesRegularExpression(
                '~/npm/[^@]+@\d+\.\d+\.\d+/~',
                $asset['url'],
                $key.' must carry an exact semver',
            );
        }
    }

    public function testEveryAssetCarriesASha256Integrity(): void
    {
        // Emitted verbatim into the attribute, so the prefix has to be there.
        foreach (cdn_assets() as $key => $asset) {
            $this->assertMatchesRegularExpression(
                '~^sha256-[A-Za-z0-9+/]{43}=$~',
                $asset['integrity'],
                $key.' must carry a base64 sha256',
            );
        }
    }

    public function testEveryAssetComesFromTheOneAllowedOrigin(): void
    {
        // http_security_headers() names exactly one third-party script origin.
        // An asset from anywhere else would be blocked by the CSP at runtime,
        // which is a failure nothing else in the suite would catch.
        foreach (cdn_assets() as $key => $asset) {
            $this->assertSame(
                'cdn.jsdelivr.net',
                parse_url($asset['url'], PHP_URL_HOST),
                $key.' must come from the origin the CSP allows',
            );
            $this->assertSame('https', parse_url($asset['url'], PHP_URL_SCHEME), $key.' must be https');
        }
    }

    public function testHashesAreDistinctPerAsset(): void
    {
        // A copy-paste that reuses a neighbour's hash blocks the asset in the
        // browser and looks like a CDN outage.
        $hashes = array_column(cdn_assets(), 'integrity');

        $this->assertSame(count($hashes), count(array_unique($hashes)));
    }

    public function testCarriesTheAssetsTheViewsAskFor(): void
    {
        $assets = cdn_assets();

        foreach (['lucide', 'chart', 'jsvectormap', 'jsvectormap_world', 'jsvectormap_css'] as $key) {
            $this->assertArrayHasKey($key, $assets);
        }
    }
}
