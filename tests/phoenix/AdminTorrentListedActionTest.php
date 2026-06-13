<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/controller/admin.torrent.listed.php';

class AdminTorrentListedActionTest extends PhoenixTestCase
{
    // 40-char hex info_hash (the action runs it through maybe_binary_to_hex).
    private const HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    /** @var array<string, mixed> */
    private array $postBackup;

    /** @var array<string, mixed> */
    private array $getBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->postBackup = $_POST;
        $this->getBackup = $_GET;
        $_POST = [];
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_POST = $this->postBackup;
        $_GET = $this->getBackup;
        mysqli_query(
            self::$connection,
            'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` = \''.self::HASH.'\';',
        );
        parent::tearDown();
    }

    private function insertTorrent(int $listed): void
    {
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
            '(`info_hash`, `name`, `size`, `listed`, `downloads`) VALUES '.
            '(\''.self::HASH.'\', \'Name\', 0, '.$listed.', 0);',
        );
    }

    private function fetchListed(): ?int
    {
        $row = mysqli_fetch_assoc(mysqli_query(
            self::$connection,
            'SELECT `listed` FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` = \''.self::HASH.'\';',
        ));

        return is_array($row) ? (int) $row['listed'] : null;
    }

    public function testListsTorrent(): void
    {
        $this->insertTorrent(0);
        $_POST = ['info_hash' => self::HASH, 'listed' => '1'];

        $message = \admin_torrent_listed_action(self::$connection, self::$settings);

        $this->assertSame(1, $this->fetchListed());
        $this->assertStringContainsString('listed', $message);
    }

    public function testUnlistsTorrent(): void
    {
        $this->insertTorrent(1);
        $_POST = ['info_hash' => self::HASH, 'listed' => '0'];

        $message = \admin_torrent_listed_action(self::$connection, self::$settings);

        $this->assertSame(0, $this->fetchListed());
        $this->assertStringContainsString('unlisted', $message);
    }

    public function testRejectsInvalidInfoHash(): void
    {
        // A non-hex info_hash bails via tracker_error (which exits) before any
        // query — exercise it in a subprocess.
        $result = $this->runPhpSubprocess(
            '<?php
            $_GET = [\'json\' => \'1\'];
            $_POST = '.var_export(['info_hash' => 'not-a-hash', 'listed' => '1'], true).';
            require_once '.var_export(dirname(__DIR__).'/bootstrap.php', true).';
            require_once '.var_export(dirname(__DIR__, 2).'/src/controller/admin.torrent.listed.php', true).';
            echo admin_torrent_listed_action($GLOBALS[\'phoenix_connection\'], $GLOBALS[\'phoenix_settings\']);
            ',
        );

        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Info Hash is invalid.', $result['stdout']);
    }
}
