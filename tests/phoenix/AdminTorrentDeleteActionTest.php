<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/controller/admin.torrent.delete.php';

class AdminTorrentDeleteActionTest extends PhoenixTestCase
{
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
        mysqli_query(self::$connection, 'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` = \''.self::HASH.'\';');
        mysqli_query(self::$connection, 'DELETE FROM `'.self::$settings['db_prefix'].'peers` WHERE `info_hash` = \''.self::HASH.'\';');
        parent::tearDown();
    }

    private function seedTorrentAndPeer(): void
    {
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
            '(`info_hash`, `name`, `size`, `listed`, `downloads`) VALUES '.
            '(\''.self::HASH.'\', \'Name\', 0, 1, 0);',
        );
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'peers` '.
            '(`info_hash`, `peer_id`, `compactv4`, `compactv6`, `portv4`, `portv6`, `state`, `updated`) VALUES '.
            '(\''.self::HASH.'\', \'__TEST_peer__\', \'\', \'\', 0, 0, 0, '.self::$time.');',
        );
    }

    private function count(string $table): int
    {
        $row = mysqli_fetch_assoc(mysqli_query(
            self::$connection,
            'SELECT COUNT(*) AS `c` FROM `'.self::$settings['db_prefix'].$table.'` WHERE `info_hash` = \''.self::HASH.'\';',
        ));

        return is_array($row) ? (int) $row['c'] : 0;
    }

    public function testDeletesTorrentAndItsPeers(): void
    {
        $this->seedTorrentAndPeer();
        $_POST = ['info_hash' => self::HASH];

        $message = \admin_torrent_delete_action(self::$connection, self::$settings);

        $this->assertSame(0, $this->count('torrents'));
        $this->assertSame(0, $this->count('peers'));
        $this->assertStringContainsString('deleted', $message);
    }

    public function testRejectsInvalidInfoHash(): void
    {
        $result = $this->runPhpSubprocess(
            '<?php
            $_GET = [\'json\' => \'1\'];
            $_POST = '.var_export(['info_hash' => 'not-a-hash'], true).';
            require_once '.var_export(dirname(__DIR__).'/bootstrap.php', true).';
            require_once '.var_export(dirname(__DIR__, 2).'/src/controller/admin.torrent.delete.php', true).';
            echo admin_torrent_delete_action($GLOBALS[\'phoenix_connection\'], $GLOBALS[\'phoenix_settings\']);
            ',
        );

        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Info Hash is invalid.', $result['stdout']);
    }
}
