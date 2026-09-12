<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class ViewAdminTasksHtmlTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/html.admin.tasks.php';
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        $settings = self::$settings;
        $settings['admin_password'] = '';
        $settings['task_retention'] = 0;

        return $settings;
    }

    /** @return list<array{id: int, name: string, value: int, source: string}> */
    private function runs(): array
    {
        return [
            ['id' => 3, 'name' => 'backup', 'value' => 1700000000, 'source' => 'cron'],
            ['id' => 2, 'name' => 'clean', 'value' => 1699999000, 'source' => 'auto'],
            ['id' => 1, 'name' => 'optimize', 'value' => 1699998000, 'source' => 'admin'],
        ];
    }

    public function testRendersEachRunWithItsTrigger(): void
    {
        $html = \view_admin_tasks_html($this->settings(), $this->runs(), 3, 0, 100, '', 'tok');

        $this->assertStringContainsString('Backed up', $html);
        $this->assertStringContainsString('Cleaned', $html);
        $this->assertStringContainsString('Optimized', $html);
        // The trigger is the column worth reading: cron vs auto vs admin.
        $this->assertStringContainsString('>Cron</span>', $html);
        $this->assertStringContainsString('>Auto</span>', $html);
        $this->assertStringContainsString('>Admin</span>', $html);
    }

    public function testShowsTheTotalAndPluralisesIt(): void
    {
        $html = \view_admin_tasks_html($this->settings(), $this->runs(), 3, 0, 100, '', 'tok');
        $this->assertStringContainsString('3 runs', $html);

        $one = [$this->runs()[0]];
        $html = \view_admin_tasks_html($this->settings(), $one, 1, 0, 100, '', 'tok');
        $this->assertStringContainsString('1 run<', $html);
    }

    public function testEmptyHistoryExplainsWhereRunsComeFrom(): void
    {
        $html = \view_admin_tasks_html($this->settings(), [], 0, 0, 100, '', 'tok');

        $this->assertStringContainsString('No maintenance has run yet', $html);
    }

    public function testFilteredEmptyStateDiffersFromNeverRun(): void
    {
        $html = \view_admin_tasks_html($this->settings(), [], 0, 0, 100, 'backup', 'tok');

        $this->assertStringContainsString('No runs recorded for this task.', $html);
    }

    public function testFilterIsMarkedSelectedAndOffersAWayOut(): void
    {
        $html = \view_admin_tasks_html($this->settings(), $this->runs(), 3, 0, 100, 'backup', 'tok');

        $this->assertStringContainsString('value="backup" selected', $html);
        $this->assertStringContainsString('href="?page=tasks"', $html);
    }

    public function testPagerAppearsOnlyWhenThereIsAnotherPage(): void
    {
        $single = \view_admin_tasks_html($this->settings(), $this->runs(), 3, 0, 100, '', 'tok');
        $this->assertStringNotContainsString('Next<span', $single);

        $paged = \view_admin_tasks_html($this->settings(), $this->runs(), 300, 0, 3, '', 'tok');
        $this->assertStringContainsString('offset=3', $paged);
    }

    public function testPagerKeepsTheFilter(): void
    {
        // Paging out of a filtered view must not silently widen it.
        $html = \view_admin_tasks_html($this->settings(), $this->runs(), 300, 0, 3, 'backup', 'tok');

        $this->assertStringContainsString('name=backup', $html);
    }

    public function testRetentionNoteReflectsTheSetting(): void
    {
        $html = \view_admin_tasks_html($this->settings(), $this->runs(), 3, 0, 100, '', 'tok');
        $this->assertStringContainsString('Every run is kept', $html);

        $settings = $this->settings();
        $settings['task_retention'] = 30;
        $html = \view_admin_tasks_html($settings, $this->runs(), 3, 0, 100, '', 'tok');
        $this->assertStringContainsString('last 30 days', $html);
    }

    public function testUnknownTaskNameStillRenders(): void
    {
        // A row written by a future Phoenix must not blank the page.
        $runs = [['id' => 1, 'name' => 'defrag', 'value' => 1700000000, 'source' => 'cron']];

        $html = \view_admin_tasks_html($this->settings(), $runs, 1, 0, 100, '', 'tok');

        $this->assertStringContainsString('Defrag', $html);
    }
}
