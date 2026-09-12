<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class NavAlertsTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__.'/../../src/functions/nav.alerts.php';
    }

    /** @return array<string, mixed> */
    private function settings(array $overrides = []): array
    {
        return array_merge(['admin_password' => 'hash', 'admin_totp_secret' => 'SECRET'], $overrides);
    }

    public function testNothingWrongIsNoAlerts(): void
    {
        $tasks = ['clean' => ['value' => 1, 'source' => 'cron', 'state' => 'ok']];

        $this->assertSame([], \nav_alerts($tasks, $this->settings()));
    }

    public function testOverdueTaskWarnsOnUtilities(): void
    {
        $tasks = ['clean' => ['value' => 1, 'source' => 'cron', 'state' => 'overdue']];

        $alerts = \nav_alerts($tasks, $this->settings());

        $this->assertSame('warning', $alerts['utilities']['level']);
        $this->assertStringContainsString('overdue', $alerts['utilities']['title']);
    }

    public function testNeverRunOutranksOverdue(): void
    {
        // One badge, so it should report the worse of the two.
        $tasks = [
            'clean' => ['value' => 1, 'source' => 'cron', 'state' => 'overdue'],
            'backup' => ['value' => 0, 'source' => '', 'state' => 'never'],
        ];

        $alerts = \nav_alerts($tasks, $this->settings());

        $this->assertSame('critical', $alerts['utilities']['level']);
        $this->assertStringContainsString('never run', $alerts['utilities']['title']);
    }

    public function testTitleCountsAndPluralises(): void
    {
        $one = \nav_alerts(['a' => ['value' => 1, 'source' => '', 'state' => 'overdue']], $this->settings());
        $this->assertSame('One maintenance task is overdue', $one['utilities']['title']);

        $two = \nav_alerts([
            'a' => ['value' => 1, 'source' => '', 'state' => 'overdue'],
            'b' => ['value' => 1, 'source' => '', 'state' => 'overdue'],
        ], $this->settings());
        $this->assertSame('2 maintenance tasks are overdue', $two['utilities']['title']);
    }

    public function testMissingTwoFactorWarnsOnSupport(): void
    {
        $alerts = \nav_alerts([], $this->settings(['admin_totp_secret' => '']));

        $this->assertSame('warning', $alerts['support']['level']);
        $this->assertStringContainsString('Two-factor', $alerts['support']['title']);
    }

    public function testEnrolledTwoFactorIsNoAlert(): void
    {
        $this->assertArrayNotHasKey('support', \nav_alerts([], $this->settings()));
    }

    public function testNoPasswordMeansNoTwoFactorNag(): void
    {
        // An install running admin_auth_optional has deliberately no auth;
        // telling it to add a second factor is noise.
        $alerts = \nav_alerts([], $this->settings(['admin_password' => '', 'admin_totp_secret' => '']));

        $this->assertArrayNotHasKey('support', $alerts);
    }
}
