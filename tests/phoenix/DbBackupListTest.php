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
        // Backups are directories now, so clear one level of nesting too.
        foreach (glob($this->dir.'*') ?: [] as $f) {
            if (is_dir($f)) {
                foreach (glob($f.'/*') ?: [] as $inner) {
                    @unlink($inner);
                }
                @rmdir($f);
                continue;
            }
            @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    /** @param list<string> $files */
    private function writeBackupDir(string $suffix, int $mtime, array $files = ['schema.sql', 'torrents.sql']): string
    {
        $path = $this->dir.self::$settings['db_name'].'.'.$suffix;
        mkdir($path);
        foreach ($files as $file) {
            file_put_contents($path.'/'.$file, '-- sql');
        }
        touch($path, $mtime);

        return $path;
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

    public function testListsDirectoryBackupsWithTheirFiles(): void
    {
        $this->writeBackupDir('20240103_000000', 1700000000, ['schema.sql', 'events.sql', 'torrents.sql']);

        $list = \db_backup_list($this->settingsWithDir());

        $this->assertCount(1, $list);
        $this->assertSame(self::$settings['db_name'].'.20240103_000000', $list[0]['name']);
        // schema first — the order a restore has to import in — then alphabetical.
        $this->assertSame(
            ['schema.sql', 'events.sql', 'torrents.sql'],
            array_column($list[0]['files'], 'name'),
        );
    }

    public function testDirectoryBackupSizeIsTheSumOfItsFiles(): void
    {
        $path = $this->writeBackupDir('20240104_000000', 1700000000, []);
        file_put_contents($path.'/schema.sql', str_repeat('a', 10));
        file_put_contents($path.'/torrents.sql', str_repeat('b', 25));

        $list = \db_backup_list($this->settingsWithDir());

        $this->assertSame(35, $list[0]['size']);
    }

    public function testLegacySingleFileBackupsStillList(): void
    {
        // An install that backed up before the split keeps its dumps listed and
        // downloadable — 'files' empty marks the entry as the download itself.
        $this->writeBackup('20240101_0000', 1600000000);

        $list = \db_backup_list($this->settingsWithDir());

        $this->assertCount(1, $list);
        $this->assertSame([], $list[0]['files']);
    }

    public function testEmptyDirectoryIsNotABackup(): void
    {
        $this->writeBackupDir('20240105_000000', 1700000000, []);

        $this->assertSame([], \db_backup_list($this->settingsWithDir()));
    }

    public function testSortsDirectoriesAndLegacyFilesTogetherNewestFirst(): void
    {
        $this->writeBackup('20240101_0000', 1600000000);
        $this->writeBackupDir('20240102_000000', 1700000000);

        $list = \db_backup_list($this->settingsWithDir());

        $this->assertSame(self::$settings['db_name'].'.20240102_000000', $list[0]['name']);
        $this->assertSame(self::$settings['db_name'].'.20240101_0000.sql', $list[1]['name']);
    }
}
