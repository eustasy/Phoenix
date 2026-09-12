<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class TaskAlertsTest extends TestCase
{
    private const NOW = 1700000000;

    public static function setUpBeforeClass(): void
    {
        require_once __DIR__.'/../../src/functions/task.alerts.php';
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        return [
            'alert_prune_after' => 3600,
            'alert_backup_after' => 172800,
            'alert_optimize_after' => 1209600,
        ];
    }

    /** @param array<string, array{value: int, source: string}> $tasks */
    private function alerts(array $tasks): array
    {
        return \task_alerts($tasks, $this->settings(), self::NOW);
    }

    public function testARecentRunIsOk(): void
    {
        $tasks = $this->alerts(['clean' => ['value' => self::NOW - 600, 'source' => 'cron']]);

        $this->assertSame('ok', $tasks['clean']['state']);
        $this->assertSame(600, $tasks['clean']['age']);
    }

    public function testAStaleRunIsOverdue(): void
    {
        $tasks = $this->alerts(['clean' => ['value' => self::NOW - 7200, 'source' => 'cron']]);

        $this->assertSame('overdue', $tasks['clean']['state']);
    }

    public function testAMonitoredTaskThatNeverRanIsReported(): void
    {
        // The state worth shouting about: a stale task usually means cron
        // stopped, a never-run one usually means the entry was never added.
        $tasks = $this->alerts([]);

        foreach (['clean', 'analyze', 'backup', 'optimize'] as $task) {
            $this->assertSame('never', $tasks[$task]['state'], $task);
            $this->assertNull($tasks[$task]['age'], $task);
        }
    }

    public function testPruneAndAnalyzeShareAThreshold(): void
    {
        // They run in the same cron, so they go stale together.
        $tasks = $this->alerts([
            'clean' => ['value' => self::NOW - 7200, 'source' => 'cron'],
            'analyze' => ['value' => self::NOW - 7200, 'source' => 'cron'],
        ]);

        $this->assertSame(3600, $tasks['clean']['after']);
        $this->assertSame(3600, $tasks['analyze']['after']);
    }

    public function testEachMonitoredTaskUsesItsOwnThreshold(): void
    {
        // A day old is fine for a backup and long overdue for a prune.
        $day = self::NOW - 86400;
        $tasks = $this->alerts([
            'clean' => ['value' => $day, 'source' => 'cron'],
            'backup' => ['value' => $day, 'source' => 'cron'],
            'optimize' => ['value' => $day, 'source' => 'cron'],
        ]);

        $this->assertSame('overdue', $tasks['clean']['state']);
        $this->assertSame('ok', $tasks['backup']['state']);
        $this->assertSame('ok', $tasks['optimize']['state']);
    }

    public function testAZeroThresholdDisablesTheAlert(): void
    {
        // An operator who prunes by hand should not be nagged forever.
        $settings = $this->settings();
        $settings['alert_prune_after'] = 0;

        $tasks = \task_alerts(['clean' => ['value' => 1, 'source' => 'admin']], $settings, self::NOW);

        $this->assertSame('ok', $tasks['clean']['state']);
    }

    public function testUnmonitoredTasksAreLeftAlone(): void
    {
        // install/migrate/check are history, not a commitment.
        $tasks = $this->alerts(['install' => ['value' => 1, 'source' => 'admin']]);

        $this->assertArrayNotHasKey('state', $tasks['install']);
    }

    public function testAFutureTimestampIsNotOverdue(): void
    {
        // A clock skewed backwards would otherwise read as wildly overdue.
        $tasks = $this->alerts(['clean' => ['value' => self::NOW + 5000, 'source' => 'cron']]);

        $this->assertSame('ok', $tasks['clean']['state']);
    }
}
