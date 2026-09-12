<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ViewAdminBackupsHtmlTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/html.admin.backups.php';
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        return ['phoenix_version' => 'Phoenix Test v.0',
            'phoenix_release' => 'Testing', 'admin_password' => 'hash'];
    }

    public function testRendersRunButtonAndCsrf(): void
    {
        $html = view_admin_backups_html($this->settings(), [], false, 'tok');
        $this->assertStringContainsString('name="process" value="backup"', $html);
        $this->assertStringContainsString('name="csrf" value="tok"', $html);
        $this->assertStringContainsString('Run backup now', $html);
    }

    public function testShowsEnvironmentCaveat(): void
    {
        $html = view_admin_backups_html($this->settings(), [], false, 'tok');
        $this->assertStringContainsString('mysqldump', $html);
    }

    public function testListsBackups(): void
    {
        $backups = [
            ['name' => 'phoenix.20240102_000000', 'size' => 2048, 'mtime' => 1700000000, 'files' => [
                ['name' => 'schema.sql.gz', 'size' => 1024],
                ['name' => 'torrents.sql.gz', 'size' => 1024],
            ]],
            ['name' => 'phoenix.20240101_0000.sql', 'size' => 1024, 'mtime' => 1699900000, 'files' => []],
        ];
        $html = view_admin_backups_html($this->settings(), $backups, false, 'tok');

        $this->assertStringContainsString('ph-card-table', $html);
        $this->assertStringContainsString('phoenix.20240102_000000', $html);
        // Rendered as a size, with the raw count kept for sorting.
        $this->assertStringContainsString('2.0 KB', $html);
        $this->assertStringContainsString('data-sort="2048"', $html);
    }

    public function testEachDumpInABackupIsItsOwnDownload(): void
    {
        // The point of the split: you can pull back one table without the rest.
        $backups = [
            ['name' => 'phoenix.20240102_000000', 'size' => 30, 'mtime' => 1700000000, 'files' => [
                ['name' => 'schema.sql.gz', 'size' => 10],
                ['name' => 'torrents.sql.gz', 'size' => 20],
            ]],
        ];
        $html = view_admin_backups_html($this->settings(), $backups, false, 'tok');

        $this->assertStringContainsString('download=phoenix.20240102_000000&amp;file=schema.sql.gz', $html);
        $this->assertStringContainsString('download=phoenix.20240102_000000&amp;file=torrents.sql.gz', $html);
        // Labelled by table, with the extension trimmed off.
        $this->assertStringContainsString('>torrents</a>', $html);
    }

    public function testLegacyBackupDownloadsAsOneFile(): void
    {
        // No 'files' means the entry is the download itself, with no file=.
        $backups = [
            ['name' => 'phoenix.20240101_0000.sql', 'size' => 1024, 'mtime' => 1699900000, 'files' => []],
        ];
        $html = view_admin_backups_html($this->settings(), $backups, false, 'tok');

        $this->assertStringContainsString('download=phoenix.20240101_0000.sql"', $html);
        $this->assertStringNotContainsString('file=', $html);
    }

    public function testEmptyShowsMessage(): void
    {
        $html = view_admin_backups_html($this->settings(), [], false, 'tok');
        $this->assertStringContainsString('No backups yet.', $html);
        $this->assertStringNotContainsString('<table', $html);
    }

    public function testMessageRenderedAndEscaped(): void
    {
        $html = view_admin_backups_html($this->settings(), [], 'Backup failed: <x>', 'tok');
        $this->assertStringContainsString('Backup failed: &lt;x&gt;', $html);
    }

    public function testMarksBackupsNavActive(): void
    {
        $html = view_admin_backups_html($this->settings(), [], false, 'tok');
        $this->assertStringContainsString('href="?page=backups" class="is-active" aria-current="page"', $html);
    }

    public function testEachBackupHasADeleteAction(): void
    {
        $backups = [
            ['name' => 'phoenix.20240102_000000', 'size' => 30, 'mtime' => 1700000000, 'files' => [
                ['name' => 'schema.sql.gz', 'size' => 10],
            ]],
        ];
        $html = view_admin_backups_html($this->settings(), $backups, false, 'tok');

        // A CSRF-protected POST with a confirm, never a link: a GET delete can
        // be fired by a prefetch or an <img> pointed at the panel.
        $this->assertStringContainsString('name="process" value="backup_delete"', $html);
        $this->assertStringContainsString('name="name" value="phoenix.20240102_000000"', $html);
        $this->assertStringContainsString('data-confirm="Delete phoenix.20240102_000000?', $html);
        $this->assertStringContainsString('is-danger', $html);
        $this->assertStringNotContainsString('href="?page=backups&amp;delete=', $html);
    }

    public function testEnvironmentNoteLinksTaskHistory(): void
    {
        // task_runs has a reader now, so the note points at it rather than
        // calling it history nothing reads.
        $html = view_admin_backups_html($this->settings(), [], false, 'tok');

        $this->assertStringContainsString('?page=tasks', $html);
        $this->assertStringNotContainsString('nothing reads', $html);
    }
}
