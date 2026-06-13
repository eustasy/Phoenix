<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/controller/admin.torrents.php';

class AdminTorrentsControllerTest extends PhoenixTestCase
{
    private const HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    /** @var array<string, mixed> */
    private array $postBackup;

    /** @var array<string, mixed> */
    private array $getBackup;

    /** @var array<string, mixed> */
    private array $sessionBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->postBackup = $_POST;
        $this->getBackup = $_GET;
        $this->sessionBackup = $_SESSION ?? [];
        $_POST = [];
        $_GET = [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_POST = $this->postBackup;
        $_GET = $this->getBackup;
        $_SESSION = $this->sessionBackup;
        mysqli_query(self::$connection, 'DELETE FROM `'.self::$settings['db_prefix'].'torrents` WHERE `info_hash` = \''.self::HASH.'\';');
        parent::tearDown();
    }

    private function insertTorrent(string $name, int $listed): void
    {
        mysqli_query(
            self::$connection,
            'INSERT INTO `'.self::$settings['db_prefix'].'torrents` '.
            '(`info_hash`, `name`, `size`, `listed`, `downloads`) VALUES '.
            '(\''.self::HASH.'\', \''.$name.'\', 0, '.$listed.', 0);',
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

    /** @return array<string, mixed> */
    private function settingsWithPassword(): array
    {
        $settings = self::$settings;
        $settings['admin_password'] = 'hash';

        return $settings;
    }

    public function testRendersTorrentsPage(): void
    {
        // No password → CSRF disabled, pure render.
        $this->insertTorrent('__TEST_listed_name__', 1);

        $html = \admin_torrents_controller(self::$connection, self::$settings);

        $this->assertStringContainsString('Torrents', $html);
        $this->assertStringContainsString('__TEST_listed_name__', $html);
        $this->assertStringContainsString('<table class="data-table">', $html);
    }

    public function testRejectsDeleteWithoutCsrf(): void
    {
        // With a password set, a delete POST lacking a valid CSRF token is
        // refused: the security message shows and the torrent survives.
        $this->insertTorrent('__TEST_keep__', 1);
        $_POST = ['process' => 'torrent_delete', 'info_hash' => self::HASH];

        $html = \admin_torrents_controller(self::$connection, $this->settingsWithPassword());

        $this->assertStringContainsString('Security check failed', $html);
        $this->assertNotNull($this->fetchListed(), 'torrent must still exist');
    }

    public function testDispatchesListedToggleWithValidCsrf(): void
    {
        $this->insertTorrent('__TEST_toggle__', 1);
        $_SESSION['phoenix_csrf'] = 'tok';
        $_POST = [
            'process' => 'torrent_listed',
            'info_hash' => self::HASH,
            'listed' => '0',
            'csrf' => 'tok',
        ];

        $html = \admin_torrents_controller(self::$connection, $this->settingsWithPassword());

        $this->assertSame(0, $this->fetchListed());
        $this->assertStringContainsString('unlisted', $html);
        $this->assertStringNotContainsString('Security check failed', $html);
    }

    public function testDispatchesDeleteWithValidCsrf(): void
    {
        $this->insertTorrent('__TEST_doomed__', 1);
        $_SESSION['phoenix_csrf'] = 'tok';
        $_POST = [
            'process' => 'torrent_delete',
            'info_hash' => self::HASH,
            'csrf' => 'tok',
        ];

        $html = \admin_torrents_controller(self::$connection, $this->settingsWithPassword());

        $this->assertNull($this->fetchListed(), 'torrent must be gone');
        $this->assertStringContainsString('deleted', $html);
    }
}
