<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class PeerSelectStrategyTest extends PhoenixTestCase
{
    /** @var array<string, mixed> */
    private array $defaultSettings = [
        'random_peers' => true,
        'random_limit' => 500,
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/peer.select.strategy.php';
    }

    public function testCompletedPeerFiltersToLeechers(): void
    {
        $peer = ['left' => 0, 'downloaded' => 100];
        $r = peer_select_strategy($peer, 5, 5, $this->defaultSettings);
        $this->assertStringContainsString("`state`='0'", $r['where']);
        $this->assertSame(' ORDER BY `left` ASC, `updated` DESC', $r['order']);
    }

    public function testJustStartedFiltersToSeeders(): void
    {
        $peer = ['left' => 1000, 'downloaded' => 100];
        $r = peer_select_strategy($peer, 5, 5, $this->defaultSettings);
        $this->assertStringContainsString("`state`='1' OR", $r['where']);
        $this->assertSame(' ORDER BY `updated` DESC', $r['order']);
    }

    public function testInProgressSmallSwarmDoesNotRandomise(): void
    {
        $peer = ['left' => 100, 'downloaded' => 1000];
        $r = peer_select_strategy($peer, 10, 10, $this->defaultSettings);
        $this->assertStringNotContainsString('RAND()', $r['order']);
    }

    public function testInProgressLargeSwarmRandomises(): void
    {
        $peer = ['left' => 100, 'downloaded' => 1000];
        $r = peer_select_strategy($peer, 300, 300, $this->defaultSettings);
        $this->assertStringContainsString('RAND()', $r['order']);
    }

    public function testRandomPeersDisabledNeverRandomises(): void
    {
        $peer = ['left' => 100, 'downloaded' => 1000];
        $r = peer_select_strategy(
            $peer,
            1000,
            1000,
            ['random_peers' => false, 'random_limit' => 500],
        );
        $this->assertStringNotContainsString('RAND()', $r['order']);
    }

    public function testUnknownStateOrdersByRecency(): void
    {
        $peer = ['left' => -1, 'downloaded' => 0];
        $r = peer_select_strategy($peer, 5, 5, $this->defaultSettings);
        $this->assertSame('', $r['where']);
        $this->assertSame(' ORDER BY `updated` DESC', $r['order']);
    }

}
