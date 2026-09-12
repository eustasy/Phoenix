<?php

declare(strict_types=1);

////	server_stats_meminfo
// Read the memory figures the sidebar gauges need out of /proc/meminfo, as
// bytes keyed by their kernel names (MemTotal, MemAvailable, SwapTotal,
// SwapFree).
//
// /proc/meminfo reports in kB, so the values are scaled here and every caller
// can treat them as bytes. Returns an empty array when the file cannot be read
// — no /proc on non-Linux, and open_basedir hides it on some shared hosts —
// which the caller reports as an unavailable gauge rather than as zero usage.

/** @return array<string, int> bytes, keyed by kernel name */
function server_stats_meminfo(): array
{
    $raw = @file_get_contents('/proc/meminfo');
    if ($raw === false) {
        return [];
    }

    $wanted = ['MemTotal' => true, 'MemAvailable' => true, 'SwapTotal' => true, 'SwapFree' => true];
    $values = [];

    foreach (explode("\n", $raw) as $line) {
        // "MemTotal:       16077516 kB"
        if (preg_match('/^(\w+):\s+(\d+)(?:\s+kB)?$/', $line, $match) !== 1) {
            continue;
        }
        if (! isset($wanted[$match[1]])) {
            continue;
        }
        // Every line this matches is reported in kB; the suffix is only absent
        // on counters (HugePages_Total and friends) that are not wanted here.
        $values[$match[1]] = intval($match[2]) * 1024;
    }

    return $values;
}
