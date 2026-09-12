<?php

declare(strict_types=1);

////	server_stats
// Collect CPU load, memory, swap and disk for the admin sidebar gauges.
//
// Every figure here is best-effort. Shared hosts routinely disable
// sys_getloadavg() and disk_free_space(), and open_basedir puts /proc out of
// reach, so each metric reports its own availability rather than the set
// failing together — a host that can read memory but not load shows one gauge
// and one "n/a", which is more use than hiding both.
//
// Each entry is:
//   'available' => bool    the figure could be read at all
//   'percent'   => ?int    0-100, null when unavailable
//   'detail'    => string  the hover text ("5.6 GB / 7.8 GB", "0.42 0.38 0.31")
//   'note'      => string  set when there is something to say instead of a
//                          percentage, e.g. swap that is switched off
//
// Swap is reported rather than hidden when the machine has none: an absent
// gauge reads as "failed to load", while "Off" is a deliberate configuration
// an operator should be able to confirm at a glance.

/** @return array<string, array{available: bool, percent: int|null, detail: string, note: string}> */
function server_stats(): array
{
    require_once __DIR__.'/format.bytes.php';
    require_once __DIR__.'/server.stats.cpuinfo.php';
    require_once __DIR__.'/server.stats.meminfo.php';

    $blank = ['available' => false, 'percent' => null, 'detail' => 'Unavailable', 'note' => 'n/a'];
    $stats = ['cpu' => $blank, 'memory' => $blank, 'swap' => $blank, 'disk' => $blank];

    ////	CPU — load average against core count
    // There is no cheap instantaneous CPU percentage in PHP, so this is load
    // over cores: 1.0 per core is "fully busy". It can exceed 100%, which is
    // real information (work is queuing), so the bar clamps but the hover does
    // not.
    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        if (is_array($load) && isset($load[0])) {
            $cpu = server_stats_cpuinfo();
            $percent = (int) round(($load[0] / $cpu['cores']) * 100);

            // The hardware goes on its own line, and only the parts the kernel
            // actually reported — a missing clock or cache is common, and a
            // zero there would read as a measurement rather than a silence.
            $hardware = [$cpu['cores'].' core'.($cpu['cores'] === 1 ? '' : 's')];
            if ($cpu['mhz'] !== null) {
                $hardware[] = sprintf('%.2f GHz', $cpu['mhz'] / 1000);
            }
            if ($cpu['cache'] !== null) {
                // Named by level, since L2 and L3 are different claims and the
                // figure is meaningless without knowing which one it is.
                $hardware[] = format_bytes($cpu['cache']).
                    ($cpu['cache_level'] !== null ? ' L'.$cpu['cache_level'] : '').' cache';
            }

            $stats['cpu'] = [
                'available' => true,
                'percent' => $percent,
                'detail' => sprintf("Load %.2f, %.2f, %.2f\n%s", $load[0], $load[1], $load[2], implode(' · ', $hardware)),
                'note' => '',
            ];
        }
    }

    ////	Memory and swap — /proc/meminfo
    // MemAvailable rather than MemFree: the kernel counts reclaimable cache as
    // free, so MemFree on a healthy box reads alarmingly low for no reason.
    $meminfo = server_stats_meminfo();
    if (isset($meminfo['MemTotal'], $meminfo['MemAvailable']) && $meminfo['MemTotal'] > 0) {
        $used = $meminfo['MemTotal'] - $meminfo['MemAvailable'];
        $stats['memory'] = [
            'available' => true,
            'percent' => (int) round(($used / $meminfo['MemTotal']) * 100),
            'detail' => format_bytes($used).' / '.format_bytes($meminfo['MemTotal']),
            'note' => '',
        ];
    }

    if (isset($meminfo['SwapTotal'], $meminfo['SwapFree'])) {
        if ($meminfo['SwapTotal'] === 0) {
            $stats['swap'] = [
                'available' => true,
                'percent' => null,
                'detail' => 'No swap configured on this machine',
                'note' => 'Off',
            ];
        } else {
            $used = $meminfo['SwapTotal'] - $meminfo['SwapFree'];
            $stats['swap'] = [
                'available' => true,
                'percent' => (int) round(($used / $meminfo['SwapTotal']) * 100),
                'detail' => format_bytes($used).' / '.format_bytes($meminfo['SwapTotal']),
                'note' => '',
            ];
        }
    }

    ////	Disk — the filesystem Phoenix is installed on
    // Not the whole machine: the partition that will actually stop the tracker
    // when it fills is the one holding the install and its backups.
    if (function_exists('disk_free_space') && function_exists('disk_total_space')) {
        $root = __DIR__.'/../..';
        $free = @disk_free_space($root);
        $total = @disk_total_space($root);
        if (is_float($free) && is_float($total) && $total > 0) {
            $used = $total - $free;
            $stats['disk'] = [
                'available' => true,
                'percent' => (int) round(($used / $total) * 100),
                'detail' => format_bytes((int) $used).' / '.format_bytes((int) $total),
                'note' => '',
            ];
        }
    }

    return $stats;
}
