<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/controller/api.torrent.update.php';
require_once __DIR__.'/../../src/model/torrent.add.php';

class ApiTorrentUpdateControllerTest extends PhoenixTestCase
{
    private const HASH = 'dddddddddddddddddddddddddddddddddddddddd';
    private const API_KEY = '__TEST_api_key__';
    private const ADMIN_KEY = '__TEST_admin_key__';

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
            'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` = \''.self::HASH.'\';',
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

    /**
     * @param array{user?: string|null, name?: string|null, size?: int, listed?: int, trackers?: string|null} $overrides
     */
    private function seed(array $overrides = []): void
    {
        \torrent_add(self::$connection, self::$settings, array_merge([
            'user' => 'tester',
            'info_hash' => self::HASH,
            'name' => 'Original',
            'size' => 100,
            'listed' => 1,
            'filename' => null,
            'files' => null,
            'trackers' => null,
            'webseeds' => null,
        ], $overrides));
    }

    public function testOwnerUpdatesOwnTorrent(): void
    {
        $this->seed();
        $_POST = ['info_hash' => self::HASH, 'name' => 'Renamed', 'size' => '500', 'listed' => '0'];

        $decoded = json_decode(\api_torrent_update_controller(self::$connection, $this->settingsWithKeys()), true);
        $this->assertIsArray($decoded);
        $this->assertSame('tester', $decoded['torrent']['user']);
        $this->assertSame('Renamed', $decoded['torrent']['name']);
        $this->assertSame(500, $decoded['torrent']['size']);
        $this->assertSame(0, $decoded['torrent']['listed']);

        $row = mysqli_fetch_assoc(mysqli_query(
            self::$connection,
            'SELECT `name`, `size`, `listed` FROM `'.self::$settings['db_prefix'].'torrents` '.
            'WHERE `info_hash` = \''.self::HASH.'\';',
        ));
        $this->assertIsArray($row);
        $this->assertSame('Renamed', $row['name']);
        $this->assertEquals(500, $row['size']);
        $this->assertEquals(0, $row['listed']);
    }

    public function testPartialUpdateKeepsUnspecifiedFields(): void
    {
        // Seed with trackers; then send only a new name. Everything not in the
        // request keeps its stored value.
        $this->seed(['trackers' => 'http://t/announce']);
        $_POST = ['info_hash' => self::HASH, 'name' => 'OnlyName'];

        $decoded = json_decode(\api_torrent_update_controller(self::$connection, $this->settingsWithKeys()), true);
        $this->assertIsArray($decoded);
        $this->assertSame('OnlyName', $decoded['torrent']['name']);
        // Untouched fields persist.
        $this->assertSame(100, $decoded['torrent']['size']);
        $this->assertSame(1, $decoded['torrent']['listed']);
        $this->assertSame(['http://t/announce'], $decoded['torrent']['trackers']);
    }

    public function testAdminUpdatesNullOwnerTorrent(): void
    {
        // An announce-created row has no owner; the '*' admin may still edit it.
        $this->seed(['user' => null]);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.self::ADMIN_KEY;
        $_POST = ['info_hash' => self::HASH, 'name' => 'AdminEdit'];

        $decoded = json_decode(\api_torrent_update_controller(self::$connection, $this->settingsWithKeys()), true);
        $this->assertIsArray($decoded);
        $this->assertSame('AdminEdit', $decoded['torrent']['name']);
        $this->assertNull($decoded['torrent']['user']);
    }

    public function testRendersXmlWhenXmlFlagSet(): void
    {
        $this->seed();
        $_GET = ['xml' => '1'];
        $_POST = ['info_hash' => self::HASH, 'name' => 'XmlName'];

        $xml = \api_torrent_update_controller(self::$connection, $this->settingsWithKeys());
        $this->assertStringStartsWith('<?xml', $xml);
        $this->assertStringContainsString('<name>XmlName</name>', $xml);
    }

    public function testRejectsUpdateOfAnotherUsersTorrent(): void
    {
        // Owned by someone else → the 'tester' key gets 'Torrent not found.',
        // and the row is untouched. tracker_error exits, so use a subprocess.
        $this->seed(['user' => 'someone_else', 'name' => 'TheirTorrent']);

        $result = $this->runErrorSubprocess(['info_hash' => self::HASH, 'name' => 'Hacked'], self::API_KEY);
        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Torrent not found.', $result['stdout']);

        $row = mysqli_fetch_assoc(mysqli_query(
            self::$connection,
            'SELECT `name` FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` = \''.self::HASH.'\';',
        ));
        $this->assertIsArray($row);
        $this->assertSame('TheirTorrent', $row['name']);
    }

    public function testRejectsNonPost(): void
    {
        $this->seed();
        $result = $this->runErrorSubprocess(['info_hash' => self::HASH], self::API_KEY, 'GET');
        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Method not allowed.', $result['stdout']);
    }

    /**
     * Run the update controller in a subprocess (tracker_error exits, which
     * would otherwise kill the PHPUnit worker). Data params ride the query
     * string; auth is the Authorization header.
     *
     * @param array<string, string> $params
     * @return array{stdout: string, stderr: string, exit: int}
     */
    private function runErrorSubprocess(array $params, string $key, string $method = 'POST'): array
    {
        $params['json'] = '1';
        $api_keys = ['tester' => hash('sha256', self::API_KEY), '*' => hash('sha256', self::ADMIN_KEY)];

        return $this->runPhpSubprocess(
            '<?php
            $_GET = '.var_export($params, true).';
            $_SERVER[\'REQUEST_METHOD\'] = '.var_export($method, true).';
            $_SERVER[\'HTTP_AUTHORIZATION\'] = '.var_export('Bearer '.$key, true).';
            require_once '.var_export(dirname(__DIR__).'/bootstrap.php', true).';
            require_once '.var_export(dirname(__DIR__, 2).'/src/controller/api.torrent.update.php', true).';
            $settings = $GLOBALS[\'phoenix_settings\'];
            $settings[\'api_keys\'] = '.var_export($api_keys, true).';
            echo api_torrent_update_controller($GLOBALS[\'phoenix_connection\'], $settings);
            ',
        );
    }
}
