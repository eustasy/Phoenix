<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class DbBackupListTest extends PhoenixTestCase
{
    private string $dir;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/db.backup.list.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/phx_baklist_'.bin2hex(random_bytes(4)).'/';
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function settingsWithDir(): array
    {
        $settings = self::$settings;
        $settings['backup_dir'] = $this->dir;

        return $settings;
    }

    private function writeBackup(string $suffix, int $mtime, string $contents = 'dump'): string
    {
        $path = $this->dir.self::$settings['db_name'].'.'.$suffix.'.sql';
        file_put_contents($path, $contents);
        touch($path, $mtime);

        return $path;
    }

    public function testListsBackupsNewestFirst(): void
    {
        $now = time();
        $this->writeBackup('20240101_0000', $now - 86400);
        $this->writeBackup('20240102_0000', $now);

        $list = db_backup_list($this->settingsWithDir());

        $this->assertCount(2, $list);
        // Newest first.
        $this->assertSame(self::$settings['db_name'].'.20240102_0000.sql', $list[0]['name']);
        $this->assertSame(self::$settings['db_name'].'.20240101_0000.sql', $list[1]['name']);
        $this->assertIsInt($list[0]['size']);
        $this->assertIsInt($list[0]['mtime']);
        $this->assertSame(4, $list[0]['size']);
    }

    public function testIgnoresNonMatchingFiles(): void
    {
        $this->writeBackup('20240101_0000', time());
        // Not the db_name.*.sql pattern → excluded.
        file_put_contents($this->dir.'unrelated.txt', 'x');
        file_put_contents($this->dir.'other_db.20240101_0000.sql', 'x');

        $list = db_backup_list($this->settingsWithDir());
        $this->assertCount(1, $list);
    }

    public function testEmptyWhenNoBackups(): void
    {
        $this->assertSame([], db_backup_list($this->settingsWithDir()));
    }
}
