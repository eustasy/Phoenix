<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/controller/api.torrent.add.php';

class ApiTorrentAddControllerTest extends PhoenixTestCase
{
    // 40-char hex info_hash (the controller runs it through
    // maybe_binary_to_hex, which accepts 40-char hex unchanged).
    private const HASH = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const API_KEY = '__TEST_api_key__';

    private int $errorReporting;

    /** @var array<string, mixed> */
    private array $getBackup;

    /** @var array<string, mixed> */
    private array $postBackup;

    protected function setUp(): void
    {
        parent::setUp();
        // Suppress the harmless "headers already sent" warning the
        // controller's header() calls would emit under PHPUnit, and
        // preserve $_GET/$_POST across tests.
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
        $settings['api_keys'] = ['tester' => self::API_KEY];

        return $settings;
    }

    public function testAddsTorrentAndRendersJsonByDefault(): void
    {
        $_GET = [];
        $_POST = [
            'key' => self::API_KEY,
            'info_hash' => self::HASH,
            'name' => 'API Torrent',
            'size' => '4096',
        ];

        $json = \api_torrent_add_controller(self::$connection, $this->settingsWithKeys());

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame('tester', $decoded['torrent']['user']);
        $this->assertSame(self::HASH, $decoded['torrent']['info_hash']);
        $this->assertSame('API Torrent', $decoded['torrent']['name']);
        $this->assertSame(4096, $decoded['torrent']['size']);
        $this->assertSame(1, $decoded['torrent']['listed']);

        // The row landed with the key's user attached.
        $row = mysqli_fetch_assoc(mysqli_query(
            self::$connection,
            'SELECT `user`, `listed` FROM `'.self::$settings['db_prefix'].'torrents` '.
            'WHERE `info_hash` = \''.self::HASH.'\';',
        ));
        $this->assertIsArray($row);
        $this->assertSame('tester', $row['user']);
        $this->assertEquals(1, $row['listed']);
    }

    public function testRendersXmlWhenXmlFlagSet(): void
    {
        $_GET = ['xml' => '1'];
        $_POST = [
            'key' => self::API_KEY,
            'info_hash' => self::HASH,
        ];

        $xml = \api_torrent_add_controller(self::$connection, $this->settingsWithKeys());

        $this->assertStringStartsWith('<?xml', $xml);
        $this->assertStringContainsString('<torrent>', $xml);
        $this->assertStringContainsString('<user>tester</user>', $xml);
        $this->assertStringContainsString('<info_hash>'.self::HASH.'</info_hash>', $xml);
    }

    public function testAcceptsParametersFromGet(): void
    {
        $_POST = [];
        $_GET = [
            'key' => self::API_KEY,
            'info_hash' => self::HASH,
            'listed' => '0',
        ];

        $json = \api_torrent_add_controller(self::$connection, $this->settingsWithKeys());

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame(0, $decoded['torrent']['listed']);
    }

    public function testRejectsDuplicateTorrent(): void
    {
        // Add once in-process, then attempt the same hash — even with a
        // different valid key — in a subprocess: add-only means the second
        // attempt errors rather than updating, and the row keeps its data.
        $_GET = [];
        $_POST = [
            'key' => self::API_KEY,
            'info_hash' => self::HASH,
            'name' => 'First',
        ];
        \api_torrent_add_controller(self::$connection, $this->settingsWithKeys());

        $result = $this->runErrorSubprocess([
            'key' => self::API_KEY,
            'info_hash' => self::HASH,
            'name' => 'Second',
        ]);

        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Torrent already exists.', $result['stdout']);

        $row = mysqli_fetch_assoc(mysqli_query(
            self::$connection,
            'SELECT `name` FROM `'.self::$settings['db_prefix'].'torrents` '.
            'WHERE `info_hash` = \''.self::HASH.'\';',
        ));
        $this->assertIsArray($row);
        $this->assertSame('First', $row['name']);
    }

    public function testRejectsInvalidKey(): void
    {
        // tracker_error() exits, so exercise the reject branch in a
        // subprocess and assert on its output + exit code.
        $result = $this->runErrorSubprocess(['key' => 'wrong-key', 'info_hash' => self::HASH]);

        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('API key is invalid.', $result['stdout']);
    }

    public function testRejectsMissingInfoHash(): void
    {
        $result = $this->runErrorSubprocess(['key' => self::API_KEY]);

        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Info Hash is invalid.', $result['stdout']);
    }

    public function testRejectsWhenApiDisabled(): void
    {
        $result = $this->runErrorSubprocess(
            ['key' => self::API_KEY, 'info_hash' => self::HASH],
            api_enabled: false,
        );

        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('API is not enabled.', $result['stdout']);
    }

    /**
     * Run the controller in a subprocess with $_GET primed (tracker_error
     * exits, which would otherwise kill the PHPUnit worker).
     *
     * @param array<string, string> $params
     * @return array{stdout: string, stderr: string, exit: int}
     */
    private function runErrorSubprocess(array $params, bool $api_enabled = true): array
    {
        $params['json'] = '1';
        $api_keys = $api_enabled ? ['tester' => self::API_KEY] : [];

        return $this->runPhpSubprocess(
            '<?php
            $_GET = '.var_export($params, true).';
            require_once '.var_export(dirname(__DIR__).'/bootstrap.php', true).';
            require_once '.var_export(dirname(__DIR__, 2).'/src/controller/api.torrent.add.php', true).';
            $settings = $GLOBALS[\'phoenix_settings\'];
            $settings[\'api_keys\'] = '.var_export($api_keys, true).';
            echo api_torrent_add_controller($GLOBALS[\'phoenix_connection\'], $settings);
            ',
        );
    }
}
