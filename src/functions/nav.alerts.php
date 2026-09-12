<?php

declare(strict_types=1);

////	nav_alerts
// Work out which sidebar entries should be carrying a warning, so a problem is
// visible from any page rather than only from the one that reports it.
//
// Two are checked, matching the two pages that hold recommendations:
//
//   DB Utilities — a monitored maintenance task that is overdue or has never
//   run. Same judgement as the dashboard's Maintenance block, from the same
//   task_alerts() output, so the badge and the table can never disagree.
//
//   Server Support — a recommended option left off. Two-factor is the one that
//   matters: the admin panel is the whole tracker, and a password alone is the
//   difference between a stolen credential being an incident and being a
//   footnote.
//
// Returns nav key => ['level' => 'warning'|'critical', 'title' => string],
// with no entry for a page that has nothing to say. The badge is an icon
// rather than a count because the number is never the point — one overdue task
// and three are the same instruction.
//
// @param array<string, array{value: int, source: string, state?: string}> $tasks
// @return array<string, array{level: string, title: string}>

/** @param PhoenixSettings $settings */
function nav_alerts(array $tasks, array $settings): array
{
    $alerts = [];

    $never = [];
    $overdue = [];
    foreach ($tasks as $task => $run) {
        $state = $run['state'] ?? null;
        if ($state === 'never') {
            $never[] = $task;
        } elseif ($state === 'overdue') {
            $overdue[] = $task;
        }
    }

    if ($never !== []) {
        $alerts['utilities'] = [
            'level' => 'critical',
            'title' => count($never) === 1
                ? 'One maintenance task has never run'
                : count($never).' maintenance tasks have never run',
        ];
    } elseif ($overdue !== []) {
        $alerts['utilities'] = [
            'level' => 'warning',
            'title' => count($overdue) === 1
                ? 'One maintenance task is overdue'
                : count($overdue).' maintenance tasks are overdue',
        ];
    }

    // Only worth saying when there is a password to add a second factor to: an
    // install running admin_auth_optional has deliberately no auth at all, and
    // telling it to enable 2FA is noise.
    if (! empty($settings['admin_password']) && empty($settings['admin_totp_secret'])) {
        $alerts['support'] = [
            'level' => 'warning',
            'title' => 'Two-factor authentication is not enabled',
        ];
    }

    return $alerts;
}
