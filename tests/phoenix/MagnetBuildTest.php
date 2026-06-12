<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class MagnetBuildTest extends PhoenixTestCase
{
    private const HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/magnet.build.php';
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function torrent(array $overrides = []): array
    {
        return array_merge([
            'info_hash' => self::HASH,
            'name' => 'Test Torrent',
            'size' => 1024,
            'trackers' => null,
            'webseeds' => null,
        ], $overrides);
    }

    public function testReturnsNullWithoutInfoHash(): void
    {
        $this->assertNull(\magnet_build($this->torrent(['info_hash' => null]), ''));
        $this->assertNull(\magnet_build($this->torrent(['info_hash' => '']), ''));
    }

    public function testMinimalMagnetIsHashOnly(): void
    {
        $magnet = \magnet_build($this->torrent(['name' => null, 'size' => 0]), '');

        $this->assertSame('magnet:?xt=urn:btih:'.self::HASH, $magnet);
    }

    public function testNameIsRawUrlEncoded(): void
    {
        $magnet = \magnet_build($this->torrent(['size' => 0]), '');

        $this->assertSame('magnet:?xt=urn:btih:'.self::HASH.'&dn=Test%20Torrent', $magnet);
    }

    public function testSizeEmittedAsExactLength(): void
    {
        $magnet = \magnet_build($this->torrent(['name' => null]), '');

        $this->assertSame('magnet:?xt=urn:btih:'.self::HASH.'&xl=1024', $magnet);
    }

    public function testAnnounceUrlLeadsTrackersEvenWithoutMeta(): void
    {
        $magnet = \magnet_build($this->torrent(['name' => null, 'size' => 0]), 'https://t.example/announce');

        $this->assertSame(
            'magnet:?xt=urn:btih:'.self::HASH.'&tr='.rawurlencode('https://t.example/announce'),
            $magnet,
        );
    }

    public function testStoredTrackersOmittedWithoutMetaFlag(): void
    {
        $magnet = \magnet_build(
            $this->torrent(['name' => null, 'size' => 0, 'trackers' => ['https://other.example/announce']]),
            '',
        );

        $this->assertSame('magnet:?xt=urn:btih:'.self::HASH, $magnet);
    }

    public function testStoredTrackersFollowAnnounceUrlWithMetaFlag(): void
    {
        $magnet = \magnet_build(
            $this->torrent(['name' => null, 'size' => 0, 'trackers' => ['https://other.example/announce']]),
            'https://t.example/announce',
            true,
        );

        $this->assertSame(
            'magnet:?xt=urn:btih:'.self::HASH.
            '&tr='.rawurlencode('https://t.example/announce').
            '&tr='.rawurlencode('https://other.example/announce'),
            $magnet,
        );
    }

    public function testAnnounceUrlDedupedAgainstStoredTrackers(): void
    {
        $magnet = \magnet_build(
            $this->torrent(['name' => null, 'size' => 0, 'trackers' => ['https://t.example/announce']]),
            'https://t.example/announce',
            true,
        );

        $this->assertSame(
            'magnet:?xt=urn:btih:'.self::HASH.'&tr='.rawurlencode('https://t.example/announce'),
            $magnet,
        );
    }

    public function testWebseedsOnlyWithMetaFlag(): void
    {
        $torrent = $this->torrent(['name' => null, 'size' => 0, 'webseeds' => ['https://seed.example/f.iso']]);

        $this->assertSame('magnet:?xt=urn:btih:'.self::HASH, \magnet_build($torrent, ''));
        $this->assertSame(
            'magnet:?xt=urn:btih:'.self::HASH.'&ws='.rawurlencode('https://seed.example/f.iso'),
            \magnet_build($torrent, '', true),
        );
    }

    public function testFullMagnetOrdering(): void
    {
        $magnet = \magnet_build(
            $this->torrent([
                'trackers' => ['https://other.example/announce'],
                'webseeds' => ['https://seed.example/f.iso'],
            ]),
            'https://t.example/announce',
            true,
        );

        $this->assertSame(
            'magnet:?xt=urn:btih:'.self::HASH.
            '&dn=Test%20Torrent'.
            '&xl=1024'.
            '&tr='.rawurlencode('https://t.example/announce').
            '&tr='.rawurlencode('https://other.example/announce').
            '&ws='.rawurlencode('https://seed.example/f.iso'),
            $magnet,
        );
    }
}
