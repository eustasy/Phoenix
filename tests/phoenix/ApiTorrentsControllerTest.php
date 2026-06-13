<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/controller/api.torrents.php';

class ApiTorrentsControllerTest extends PhoenixTestCase
{
    private const API_KEY = '__TEST_api_key__';
    private const LISTED = '__TEST_listed_torrent__';
    private const UNLISTED = '__TEST_unlisted_torrent__';

    private int $errorReporting;

    /** @var array<string, mixed> */
    private array $getBackup;

    /** @var array<string, mixed> */
    private array $postBackup;

    protected function setUp(): void
    {
        parent::setUp();
        // Suppress the harmless "headers already sent" warning the controller's
        // header() calls would emit under PHPUnit, and preserve $_GET/$_POST.
        $this->errorReporting = error_reporting();
        $this->getBackup = $_GET;
        $this->postBackup = $_POST;
        error_reporting(0);

        $this->insertTorrent(self::LISTED, 'alice', 'Listed One', 1024, 1, 3);
        $this->insertTorrent(self::UNLISTED, 'bob', 'Unlisted One', 2048, 0, 0);
    }

    protected function tearDown(): void
    {
        error_reporting($this->errorReporting);
        $_GET = $this->getBackup;
        $_POST = $this->postBackup;
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` LIKE \'__TEST_%\';',
        );
        parent::tearDown();
    }

    private function insertTorrent(string $infoHash, string $user, string $name, int $size, int $listed, int $downloads): void
    {
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
            '(`info_hash`, `user`, `name`, `size`, `listed`, `downloads`) VALUES '.
            '(\''.$infoHash.'\', \''.$user.'\', \''.$name.'\', '.$size.', '.$listed.', '.$downloads.');',
        );
    }

    /** @return array<string, mixed> */
    private function settingsWithKeys(): array
    {
        $settings = self::$settings;
        $settings['api_keys'] = ['tester' => self::API_KEY];

        return $settings;
    }

    /**
     * Find one rendered JSON row by info_hash.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function findRow(array $rows, string $infoHash): array
    {
        foreach ($rows as $row) {
            if (($row['info_hash'] ?? null) === $infoHash) {
                return $row;
            }
        }

        return [];
    }

    public function testListsAllTorrentsAsJsonByDefault(): void
    {
        $_GET = [];
        $_POST = ['key' => self::API_KEY];

        $decoded = json_decode(\api_torrents_controller(self::$connection, $this->settingsWithKeys()), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('torrents', $decoded);

        $listed = $this->findRow($decoded['torrents'], self::LISTED);
        $this->assertSame('alice', $listed['user']);
        $this->assertSame(1, $listed['listed']);
        $this->assertSame(1024, $listed['size']);

        // The unlisted torrent is present too — the endpoint returns everything.
        $unlisted = $this->findRow($decoded['torrents'], self::UNLISTED);
        $this->assertSame('bob', $unlisted['user']);
        $this->assertSame(0, $unlisted['listed']);
    }

    public function testRendersXmlWhenXmlFlagSet(): void
    {
        $_POST = ['key' => self::API_KEY];
        $_GET = ['xml' => '1'];

        $xml = \api_torrents_controller(self::$connection, $this->settingsWithKeys());

        $this->assertStringStartsWith('<?xml', $xml);
        $this->assertStringContainsString('<torrents>', $xml);
        $this->assertStringContainsString('<info_hash>'.self::LISTED.'</info_hash>', $xml);
        $this->assertStringContainsString('<user>bob</user>', $xml);
        $this->assertStringContainsString('<listed>0</listed>', $xml);
    }

    public function testAcceptsKeyFromGet(): void
    {
        $_POST = [];
        $_GET = ['key' => self::API_KEY];

        $decoded = json_decode(\api_torrents_controller(self::$connection, $this->settingsWithKeys()), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('torrents', $decoded);
    }

    public function testRejectsInvalidKey(): void
    {
        // The controller authenticates before any DB work; tracker_error()
        // exits, so exercise the reject branch in a subprocess.
        $result = $this->runPhpSubprocess(
            '<?php
            $_GET = '.var_export(['key' => 'wrong-key', 'json' => '1'], true).';
            require_once '.var_export(dirname(__DIR__).'/bootstrap.php', true).';
            require_once '.var_export(dirname(__DIR__, 2).'/src/controller/api.torrents.php', true).';
            $settings = $GLOBALS[\'phoenix_settings\'];
            $settings[\'api_keys\'] = '.var_export(['tester' => self::API_KEY], true).';
            echo api_torrents_controller($GLOBALS[\'phoenix_connection\'], $settings);
            ',
        );

        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('API key is invalid.', $result['stdout']);
    }
}
