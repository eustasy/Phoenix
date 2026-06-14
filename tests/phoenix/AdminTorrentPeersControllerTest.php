<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/controller/admin.torrent.peers.php';
require_once __DIR__.'/../../src/model/peer.insert.php'; // for insertPeer()

class AdminTorrentPeersControllerTest extends PhoenixTestCase
{
    // The controller validates info_hash as 40-char hex, so the fixtures use
    // hashes that actually pass that check.
    private const HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const EMPTY_HASH = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    /** @var array<string, mixed> */
    private array $getBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->getBackup = $_GET;
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_GET = $this->getBackup;
        foreach ([self::HASH, self::EMPTY_HASH] as $hash) {
            mysqli_query(
                self::$connection,
                'DELETE FROM `'.self::$settings['db_prefix'].'peers` WHERE `info_hash` = \''.$hash.'\';',
            );
        }
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        // admin_password empty → CSRF disabled, so no session is needed.
        $settings = self::$settings;
        $settings['admin_password'] = '';

        return $settings;
    }

    public function testRendersSwarmWithDetectedClient(): void
    {
        // A real Transmission peer_id (20 bytes), stored as 40-char hex.
        $peer_id = bin2hex('-TR4110-abcdefghijkl');
        $this->insertPeer(self::HASH, $peer_id, 1, 1700000000);

        $_GET['info_hash'] = self::HASH;
        $html = \admin_torrent_peers_controller(self::$connection, $this->settings());

        $this->assertStringContainsString('<title>Phoenix Admin: Peers</title>', $html);
        $this->assertStringContainsString('Transmission', $html);
        $this->assertStringContainsString('Seeding', $html);
    }

    public function testRendersEmptySwarm(): void
    {
        $_GET['info_hash'] = self::EMPTY_HASH;
        $html = \admin_torrent_peers_controller(self::$connection, $this->settings());

        $this->assertStringContainsString('No active peers.', $html);
    }
}
