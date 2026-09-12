<?php

declare(strict_types=1);

////	task_alerts
// Decide which maintenance tasks the dashboard should be complaining about.
//
// Four tasks are monitored, because four are the ones a tracker quietly stops
// doing: pruning and analyzing share the frequent cron so they share a
// threshold, while backup and optimize each have their own. Everything else a
// task_log() records — install, migrate, check — is history rather than a
// commitment, and is reported without judgement.
//
// A monitored task is returned whether or not it has ever run. "Never" is the
// state worth shouting about: a task that has run and gone stale usually means
// cron stopped, while one that has never run at all usually means the crontab
// entry was never added, and an absent row would say neither.
//
// Each monitored entry gains:
//   'state'  => 'ok' | 'overdue' | 'never'
//   'age'    => ?int  seconds since the last run, null when never run
//   'after'  => int   the threshold it is judged against, 0 when disabled
//
// A threshold of 0 disables that task's alert, so an operator who prunes by
// hand is not nagged forever.
//
// @param array<string, array{value: int, source: string}> $tasks
// @return array<string, array{value: int, source: string, state?: string, age?: int|null, after?: int}>

/** @param PhoenixSettings $settings */
function task_alerts(array $tasks, array $settings, int $time): array
{
    $monitored = [
        'clean' => intval($settings['alert_prune_after']),
        'analyze' => intval($settings['alert_prune_after']),
        'backup' => intval($settings['alert_backup_after']),
        'optimize' => intval($settings['alert_optimize_after']),
    ];

    foreach ($monitored as $task => $after) {
        if (! isset($tasks[$task])) {
            $tasks[$task] = ['value' => 0, 'source' => '', 'state' => 'never', 'age' => null, 'after' => $after];
            continue;
        }

        $age = $time - $tasks[$task]['value'];
        $tasks[$task]['age'] = $age;
        $tasks[$task]['after'] = $after;
        // A clock skewed backwards would otherwise read as wildly overdue.
        $tasks[$task]['state'] = ($after > 0 && $age > $after) ? 'overdue' : 'ok';
    }

    return $tasks;
}
