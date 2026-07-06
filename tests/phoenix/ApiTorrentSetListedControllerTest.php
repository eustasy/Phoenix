<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/controller/api.torrent.set.listed.php';

class ApiTorrentSetListedControllerTest extends PhoenixTestCase
{
    private const API_KEY = '__TEST_api_key__';
    private const ADMIN_KEY = '__TEST_admin_key__';

    // 40-char hex info_hashes (the controller runs them through
    // maybe_binary_to_hex, which accepts 40-char hex unchanged).
    private const HASH_OWN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const HASH_OTHER = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const HASH_UNOWNED = 'cccccccccccccccccccccccccccccccccccccccc';

    private int $errorReporting;

    /** @var array<string, mixed> */
    private array $getBackup;

    /** @var array<string, mixed> */
    private array $postBackup;

    /** @var array<string, mixed> */
    private array $serverBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->errorReporting = error_reporting();
        $this->getBackup = $_GET;
        $this->postBackup = $_POST;
        $this->serverBackup = $_SERVER;
        error_reporting(0);
        $_GET = [];
        $_POST = [];
        // POST + a valid owner ('tester') key by default; individual tests
        // override the Authorization header where they need the admin.
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.self::API_KEY;
        unset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }

    protected function tearDown(): void
    {
        error_reporting($this->errorReporting);
        $_GET = $this->getBackup;
        $_POST = $this->postBackup;
        $_SERVER = $this->serverBackup;
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` IN '.
            '(\''.self::HASH_OWN.'\', \''.self::HASH_OTHER.'\', \''.self::HASH_UNOWNED.'\');',
        );
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function settingsWithKeys(): array
    {
        $settings = self::$settings;
        $settings['api_keys'] = ['tester' => hash('sha256', self::API_KEY), '*' => hash('sha256', self::ADMIN_KEY)];

        return $settings;
    }

    private function insertTorrent(string $infoHash, ?string $user, int $listed): void
    {
        $userSql = $user === null ? 'NULL' : '\''.mysqli_real_escape_string(self::$connection, $user).'\'';
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
            '(`info_hash`, `user`, `name`, `size`, `listed`, `downloads`) VALUES '.
            '(\''.$infoHash.'\', '.$userSql.', \'Name\', 0, '.$listed.', 0);',
        );
    }

    private function fetchListed(string $infoHash): ?int
    {
        $row = mysqli_fetch_assoc(mysqli_query(
            self::$connection,
            'SELECT `listed` FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` = \''.$infoHash.'\';',
        ));

        return is_array($row) ? (int) $row['listed'] : null;
    }

    public function testListsOwnTorrent(): void
    {
        $this->insertTorrent(self::HASH_OWN, 'tester', 0);
        $_POST = ['info_hash' => self::HASH_OWN];

        $json = \api_torrent_set_listed_controller(self::$connection, $this->settingsWithKeys(), 1);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame(self::HASH_OWN, $decoded['torrent']['info_hash']);
        $this->assertSame('tester', $decoded['torrent']['user']);
        $this->assertSame(1, $decoded['torrent']['listed']);
        $this->assertSame(1, $this->fetchListed(self::HASH_OWN));
    }

    public function testDelistsOwnTorrent(): void
    {
        $this->insertTorrent(self::HASH_OWN, 'tester', 1);
        $_POST = ['info_hash' => self::HASH_OWN];

        $json = \api_torrent_set_listed_controller(self::$connection, $this->settingsWithKeys(), 0);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame(0, $decoded['torrent']['listed']);
        $this->assertSame(0, $this->fetchListed(self::HASH_OWN));
    }

    public function testAdminListsUnownedTorrent(): void
    {
        // The '*' admin key flips a null-owner torrent; the response carries a
        // null user.
        $this->insertTorrent(self::HASH_UNOWNED, null, 0);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.self::ADMIN_KEY;
        $_POST = ['info_hash' => self::HASH_UNOWNED];

        $json = \api_torrent_set_listed_controller(self::$connection, $this->settingsWithKeys(), 1);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertNull($decoded['torrent']['user']);
        $this->assertSame(1, $decoded['torrent']['listed']);
        $this->assertSame(1, $this->fetchListed(self::HASH_UNOWNED));
    }

    public function testRendersXmlWhenXmlFlagSet(): void
    {
        $this->insertTorrent(self::HASH_OWN, 'tester', 0);
        $_POST = ['info_hash' => self::HASH_OWN];
        $_GET = ['xml' => '1'];

        $xml = \api_torrent_set_listed_controller(self::$connection, $this->settingsWithKeys(), 1);
        $this->assertStringStartsWith('<?xml', $xml);
        $this->assertStringContainsString('<info_hash>'.self::HASH_OWN.'</info_hash>', $xml);
        $this->assertStringContainsString('<listed>1</listed>', $xml);
    }

    public function testAcceptsInfoHashFromQueryString(): void
    {
        // Auth is the header; the data params may still ride the query string on
        // a POST request (the controller reads $_POST ?? $_GET).
        $this->insertTorrent(self::HASH_OWN, 'tester', 0);
        $_POST = [];
        $_GET = ['info_hash' => self::HASH_OWN];

        $json = \api_torrent_set_listed_controller(self::$connection, $this->settingsWithKeys(), 1);
        $this->assertSame(1, json_decode($json, true)['torrent']['listed']);
    }

    public function testRejectsMissingInfoHash(): void
    {
        $result = $this->runErrorSubprocess([]);
        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Info Hash is invalid.', $result['stdout']);
    }

    public function testRejectsAnotherUsersTorrent(): void
    {
        // Owned by someone else: a non-admin key gets the same 'Torrent not
        // found.' a missing row would, so ownership never discloses existence.
        $this->insertTorrent(self::HASH_OTHER, 'other', 1);
        $result = $this->runErrorSubprocess(['info_hash' => self::HASH_OTHER]);

        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Torrent not found.', $result['stdout']);
        $this->assertSame(1, $this->fetchListed(self::HASH_OTHER));
    }

    public function testRejectsUnownedTorrentForNonAdmin(): void
    {
        // Null-owner rows are admin-only; a normal key can't reach them.
        $this->insertTorrent(self::HASH_UNOWNED, null, 1);
        $result = $this->runErrorSubprocess(['info_hash' => self::HASH_UNOWNED]);

        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Torrent not found.', $result['stdout']);
        $this->assertSame(1, $this->fetchListed(self::HASH_UNOWNED));
    }

    public function testRejectsNonPost(): void
    {
        $result = $this->runErrorSubprocess(['info_hash' => self::HASH_OWN], method: 'GET');
        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Method not allowed.', $result['stdout']);
    }

    /**
     * Run the controller in a subprocess (tracker_error exits). Authenticates
     * with the valid 'tester' key via the Authorization header; $params carry
     * the data fields. $listed defaults to 1 (list); $method to POST.
     *
     * @param array<string, string> $params
     * @return array{stdout: string, stderr: string, exit: int}
     */
    private function runErrorSubprocess(array $params, int $listed = 1, string $method = 'POST'): array
    {
        $params['json'] = '1';
        $api_keys = ['tester' => hash('sha256', self::API_KEY), '*' => hash('sha256', self::ADMIN_KEY)];

        return $this->runPhpSubprocess(
            '<?php
            $_GET = '.var_export($params, true).';
            $_SERVER[\'REQUEST_METHOD\'] = '.var_export($method, true).';
            $_SERVER[\'HTTP_AUTHORIZATION\'] = '.var_export('Bearer '.self::API_KEY, true).';
            require_once '.var_export(dirname(__DIR__).'/bootstrap.php', true).';
            require_once '.var_export(dirname(__DIR__, 2).'/src/controller/api.torrent.set.listed.php', true).';
            $settings = $GLOBALS[\'phoenix_settings\'];
            $settings[\'api_keys\'] = '.var_export($api_keys, true).';
            echo api_torrent_set_listed_controller($GLOBALS[\'phoenix_connection\'], $settings, '.$listed.');
            ',
        );
    }
}
