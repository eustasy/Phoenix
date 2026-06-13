<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/controller/api.torrents.php';

class ApiTorrentsControllerTest extends PhoenixTestCase
{
    private const API_KEY = '__TEST_api_key__';
    private const ADMIN_KEY = '__TEST_admin_key__';
    private const OWN = '__TEST_own_torrent__';
    private const OTHER = '__TEST_other_torrent__';

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
        // Suppress the "headers already sent" warning the controller's header()
        // calls emit, and preserve the superglobals it reads.
        $this->errorReporting = error_reporting();
        $this->getBackup = $_GET;
        $this->postBackup = $_POST;
        $this->serverBackup = $_SERVER;
        error_reporting(0);
        $_GET = [];
        $_POST = [];
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->insertTorrent(self::OWN, 'tester', 1);
        $this->insertTorrent(self::OTHER, 'other', 0);
    }

    protected function tearDown(): void
    {
        error_reporting($this->errorReporting);
        $_GET = $this->getBackup;
        $_POST = $this->postBackup;
        $_SERVER = $this->serverBackup;
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` LIKE \'__TEST_%\';',
        );
        parent::tearDown();
    }

    private function insertTorrent(string $infoHash, string $user, int $listed): void
    {
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
            '(`info_hash`, `user`, `name`, `size`, `listed`, `downloads`) VALUES '.
            '(\''.$infoHash.'\', \''.$user.'\', \'Name\', 0, '.$listed.', 0);',
        );
    }

    /** @return array<string, mixed> */
    private function settingsWithKeys(): array
    {
        $settings = self::$settings;
        $settings['api_keys'] = ['tester' => self::API_KEY, '*' => self::ADMIN_KEY];

        return $settings;
    }

    /** @return list<string> the info_hashes the controller returned as JSON */
    private function listHashesFor(string $key): array
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.$key;
        $decoded = json_decode(\api_torrents_controller(self::$connection, $this->settingsWithKeys()), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('torrents', $decoded);

        return array_column($decoded['torrents'], 'info_hash');
    }

    public function testNormalKeySeesOnlyOwnTorrents(): void
    {
        $hashes = $this->listHashesFor(self::API_KEY);
        $this->assertContains(self::OWN, $hashes);
        $this->assertNotContains(self::OTHER, $hashes);
    }

    public function testAdminKeySeesAllTorrents(): void
    {
        $hashes = $this->listHashesFor(self::ADMIN_KEY);
        $this->assertContains(self::OWN, $hashes);
        $this->assertContains(self::OTHER, $hashes);
    }

    public function testRendersXmlWhenXmlFlagSet(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.self::ADMIN_KEY;
        $_GET = ['xml' => '1'];

        $xml = \api_torrents_controller(self::$connection, $this->settingsWithKeys());
        $this->assertStringStartsWith('<?xml', $xml);
        $this->assertStringContainsString('<info_hash>'.self::OWN.'</info_hash>', $xml);
        $this->assertStringContainsString('<info_hash>'.self::OTHER.'</info_hash>', $xml);
    }

    public function testRejectsNonGet(): void
    {
        // POST to a GET-only endpoint → Method not allowed (tracker_error exits).
        $result = $this->runErrorSubprocess('POST', 'Bearer '.self::API_KEY);
        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Method not allowed.', $result['stdout']);
    }

    public function testRejectsMissingAuth(): void
    {
        $result = $this->runErrorSubprocess('GET', null);
        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Authorization required.', $result['stdout']);
    }

    /**
     * @return array{stdout: string, stderr: string, exit: int}
     */
    private function runErrorSubprocess(string $method, ?string $authHeader): array
    {
        $api_keys = ['tester' => self::API_KEY, '*' => self::ADMIN_KEY];
        $hdr = $authHeader === null
            ? ''
            : '$_SERVER[\'HTTP_AUTHORIZATION\'] = '.var_export($authHeader, true).';';

        return $this->runPhpSubprocess(
            '<?php
            $_GET = [\'json\' => \'1\'];
            $_SERVER[\'REQUEST_METHOD\'] = '.var_export($method, true).';
            '.$hdr.'
            require_once '.var_export(dirname(__DIR__).'/bootstrap.php', true).';
            require_once '.var_export(dirname(__DIR__, 2).'/src/controller/api.torrents.php', true).';
            $settings = $GLOBALS[\'phoenix_settings\'];
            $settings[\'api_keys\'] = '.var_export($api_keys, true).';
            echo api_torrents_controller($GLOBALS[\'phoenix_connection\'], $settings);
            ',
        );
    }
}
