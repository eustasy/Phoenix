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
        return ['phoenix_version' => 'Phoenix Test v.0', 'admin_password' => 'hash'];
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
            ['name' => 'phoenix.20240102_0000.sql', 'size' => 2048, 'mtime' => 1700000000],
            ['name' => 'phoenix.20240101_0000.sql', 'size' => 1024, 'mtime' => 1699900000],
        ];
        $html = view_admin_backups_html($this->settings(), $backups, false, 'tok');

        $this->assertStringContainsString('<table class="data-table">', $html);
        $this->assertStringContainsString('phoenix.20240102_0000.sql', $html);
        $this->assertStringContainsString('2,048 bytes', $html);
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

    public function testUsesWideLayoutAndMarksBackupsNavActive(): void
    {
        $html = view_admin_backups_html($this->settings(), [], false, 'tok');
        $this->assertStringContainsString('<body class="wide">', $html);
        $this->assertStringContainsString('href="?page=backups" class="nav-link current" aria-current="page"', $html);
    }
}
