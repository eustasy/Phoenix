<?php

declare(strict_types=1);

////	server_stats_cores
// How many CPUs the load average should be divided by, for the sidebar gauge.
//
// Counts `processor` lines in /proc/cpuinfo, which is the only reading that
// needs no shell. Falls back to 1 when /proc is unreadable — open_basedir and
// non-Linux hosts both hide it — so the CPU gauge reads a single-core machine's
// load rather than dividing by a guess. That over-reports on a busy multi-core
// host, which is the safer direction to be wrong in for a warning gauge.

function server_stats_cores(): int
{
    $cpuinfo = @file_get_contents('/proc/cpuinfo');
    if ($cpuinfo === false) {
        return 1;
    }

    $count = preg_match_all('/^processor\s*:/m', $cpuinfo);

    return $count > 0 ? $count : 1;
}
