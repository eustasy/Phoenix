<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/controller/admin.clients.php';
require_once __DIR__.'/../../src/model/peer.insert.php'; // for insertPeer()

class AdminClientsControllerTest extends PhoenixTestCase
{
    private const HASH = '__TEST_acc__';

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
        $prefix = self::$settings['db_prefix'];
        mysqli_query(self::$connection, 'DELETE FROM `'.$prefix.'peers` WHERE `info_hash` = \''.self::HASH.'\';');
        mysqli_query(self::$connection, 'DELETE FROM `'.$prefix.'events` WHERE `info_hash` = \''.self::HASH.'\';');
        parent::tearDown();
    }

    private function peer(string $peerId): void
    {
        // peer_id reaches the table as hex, the same form the tracker stores.
        $this->insertPeer(self::HASH, bin2hex(substr(str_pad($peerId, 20, 'x'), 0, 20)), 1, time());
    }

    private function completion(string $client): void
    {
        mysqli_execute_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'events` '.
            '(`time`, `info_hash`, `event`, `client`) VALUES (?, ?, \'completed\', ?);',
            [time(), self::HASH, $client],
        );
    }

    public function testDefaultsToTheLiveSwarm(): void
    {
        $this->peer('-TR4130-aaaaaaaa');

        $html = \admin_clients_controller(self::$connection, self::$settings);

        $this->assertStringContainsString('Clients', $html);
        // The live metric is the selected segment.
        $this->assertStringContainsString('metric=live', $html);
        $this->assertStringContainsString('Transmission', $html);
    }

    public function testUnknownMetricFallsBackToLive(): void
    {
        // Anything but the literal 'events' is the live swarm, so a junk value
        // cannot reach the models.
        $_GET['metric'] = 'nonsense';
        $this->peer('-TR4130-aaaaaaaa');

        $html = \admin_clients_controller(self::$connection, self::$settings);

        $this->assertStringContainsString('Transmission', $html);
    }

    public function testEventsMetricReadsTheLedger(): void
    {
        $_GET['metric'] = 'events';
        $this->completion('__TEST_Ledger 1.0');
        // A live peer that must NOT appear under the all-time metric.
        $this->peer('-TR4130-aaaaaaaa');

        $html = \admin_clients_controller(self::$connection, self::$settings);

        $this->assertStringContainsString('__TEST_Ledger', $html);
        $this->assertStringContainsString('metric=events', $html);
    }

    public function testEmptySwarmRendersTheEmptyState(): void
    {
        $html = \admin_clients_controller(self::$connection, self::$settings);

        $this->assertStringContainsString('Clients', $html);
    }

    public function testCsrfTokenOnlyWhenAPasswordIsSet(): void
    {
        $settings = self::$settings;
        $settings['admin_password'] = 'hash';

        $withPassword = \admin_clients_controller(self::$connection, $settings);
        $without = \admin_clients_controller(self::$connection, self::$settings);

        $this->assertStringContainsString('name="csrf"', $withPassword);
        $this->assertStringNotContainsString('name="csrf"', $without);
    }
}
