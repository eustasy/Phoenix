<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/controller/api.torrent.delete.php';

class ApiTorrentDeleteControllerTest extends PhoenixTestCase
{
    private const API_KEY = '__TEST_api_key__';
    private const ADMIN_KEY = '__TEST_admin_key__';

    private const HASH_OWN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const HASH_OTHER = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const HASH_UNOWNED = 'cccccccccccccccccccccccccccccccccccccccc';

    private int $errorReporting;

    /** @var array<string, mixed> */
    private array $getBackup;

    /** @var array<string, mixed> */
    private array $postBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->errorReporting = error_reporting();
        $this->getBackup = $_GET;
        $this->postBackup = $_POST;
        error_reporting(0);
    }

    protected function tearDown(): void
    {
        error_reporting($this->errorReporting);
        $_GET = $this->getBackup;
        $_POST = $this->postBackup;
        $hashes = '(\''.self::HASH_OWN.'\', \''.self::HASH_OTHER.'\', \''.self::HASH_UNOWNED.'\')';
        mysqli_query(self::$connection, 'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` IN '.$hashes.';');
        mysqli_query(self::$connection, 'DELETE FROM `'.self::$settings['db_prefix'].'peers` WHERE `info_hash` IN '.$hashes.';');
        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsWithKeys(bool $allowDelete): array
    {
        $settings = self::$settings;
        $settings['api_keys'] = ['tester' => self::API_KEY, '*' => self::ADMIN_KEY];
        $settings['api_allow_delete'] = $allowDelete;

        return $settings;
    }

    private function insertTorrent(string $infoHash, ?string $user): void
    {
        $userSql = $user === null ? 'NULL' : '\''.mysqli_real_escape_string(self::$connection, $user).'\'';
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
            '(`info_hash`, `user`, `name`, `size`, `listed`, `downloads`) VALUES '.
            '(\''.$infoHash.'\', '.$userSql.', \'Name\', 0, 1, 0);',
        );
    }

    private function insertPeerRow(string $infoHash, string $peerId): void
    {
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
            '(`info_hash`, `peer_id`, `compactv4`, `compactv6`, `portv4`, `portv6`, `state`, `updated`) VALUES '.
            '(\''.$infoHash.'\', \''.$peerId.'\', \'\', \'\', 0, 0, 0, '.self::$time.');',
        );
    }

    private function torrentExists(string $infoHash): bool
    {
        $row = mysqli_fetch_assoc(mysqli_query(
            self::$connection,
            'SELECT 1 AS `x` FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` = \''.$infoHash.'\';',
        ));

        return is_array($row);
    }

    private function peerCount(string $infoHash): int
    {
        $row = mysqli_fetch_assoc(mysqli_query(
            self::$connection,
            'SELECT COUNT(*) AS `c` FROM `'.self::$settings['db_prefix'].'peers` WHERE `info_hash` = \''.$infoHash.'\';',
        ));

        return is_array($row) ? (int) $row['c'] : 0;
    }

    public function testDeletesOwnTorrentAndPeersWhenAllowed(): void
    {
        $this->insertTorrent(self::HASH_OWN, 'tester');
        $this->insertPeerRow(self::HASH_OWN, '__TEST_peer_1__');
        $this->insertPeerRow(self::HASH_OWN, '__TEST_peer_2__');

        $_GET = [];
        $_POST = ['key' => self::API_KEY, 'info_hash' => self::HASH_OWN];

        $json = \api_torrent_delete_controller(self::$connection, $this->settingsWithKeys(true));
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        // The deleted row is rendered as it was.
        $this->assertSame(self::HASH_OWN, $decoded['torrent']['info_hash']);
        $this->assertSame('tester', $decoded['torrent']['user']);

        // Row and its peers are gone.
        $this->assertFalse($this->torrentExists(self::HASH_OWN));
        $this->assertSame(0, $this->peerCount(self::HASH_OWN));
    }

    public function testAdminDeletesUnownedTorrentEvenWhenDisabled(): void
    {
        // Deletion is off, but the '*' admin is exempt and may delete a
        // null-owner torrent.
        $this->insertTorrent(self::HASH_UNOWNED, null);
        $_GET = [];
        $_POST = ['key' => self::ADMIN_KEY, 'info_hash' => self::HASH_UNOWNED];

        $json = \api_torrent_delete_controller(self::$connection, $this->settingsWithKeys(false));
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertNull($decoded['torrent']['user']);
        $this->assertFalse($this->torrentExists(self::HASH_UNOWNED));
    }

    public function testRendersXmlWhenXmlFlagSet(): void
    {
        $this->insertTorrent(self::HASH_OWN, 'tester');
        $_POST = ['key' => self::API_KEY, 'info_hash' => self::HASH_OWN];
        $_GET = ['xml' => '1'];

        $xml = \api_torrent_delete_controller(self::$connection, $this->settingsWithKeys(true));
        $this->assertStringStartsWith('<?xml', $xml);
        $this->assertStringContainsString('<info_hash>'.self::HASH_OWN.'</info_hash>', $xml);
    }

    public function testRejectsWhenDeletionDisabledForNonAdmin(): void
    {
        // Gate fires before any lookup; the torrent survives.
        $this->insertTorrent(self::HASH_OWN, 'tester');
        $result = $this->runErrorSubprocess(['key' => self::API_KEY, 'info_hash' => self::HASH_OWN], allowDelete: false);

        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Torrent deletion is disabled.', $result['stdout']);
        $this->assertTrue($this->torrentExists(self::HASH_OWN));
    }

    public function testRejectsAnotherUsersTorrent(): void
    {
        // Deletion enabled, but the torrent is someone else's: 'Torrent not
        // found.' and the row survives.
        $this->insertTorrent(self::HASH_OTHER, 'other');
        $result = $this->runErrorSubprocess(['key' => self::API_KEY, 'info_hash' => self::HASH_OTHER], allowDelete: true);

        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Torrent not found.', $result['stdout']);
        $this->assertTrue($this->torrentExists(self::HASH_OTHER));
    }

    public function testRejectsMissingInfoHash(): void
    {
        $result = $this->runErrorSubprocess(['key' => self::API_KEY], allowDelete: true);
        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Info Hash is invalid.', $result['stdout']);
    }

    /**
     * @param array<string, string> $params
     * @return array{stdout: string, stderr: string, exit: int}
     */
    private function runErrorSubprocess(array $params, bool $allowDelete): array
    {
        $params['json'] = '1';
        $api_keys = ['tester' => self::API_KEY, '*' => self::ADMIN_KEY];

        return $this->runPhpSubprocess(
            '<?php
            $_GET = '.var_export($params, true).';
            require_once '.var_export(dirname(__DIR__).'/bootstrap.php', true).';
            require_once '.var_export(dirname(__DIR__, 2).'/src/controller/api.torrent.delete.php', true).';
            $settings = $GLOBALS[\'phoenix_settings\'];
            $settings[\'api_keys\'] = '.var_export($api_keys, true).';
            $settings[\'api_allow_delete\'] = '.var_export($allowDelete, true).';
            echo api_torrent_delete_controller($GLOBALS[\'phoenix_connection\'], $settings);
            ',
        );
    }
}
