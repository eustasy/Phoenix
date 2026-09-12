<?php

declare(strict_types=1);

////	server_stats_cpuinfo
// Read what /proc/cpuinfo will say about the processor, for the sidebar's CPU
// gauge and its hover.
//
// Returns:
//   'cores' => int    always at least 1
//   'mhz'   => ?float clock speed, null when the kernel does not report one
//   'cache' => ?int   last-level cache in bytes, null when not reported
//   'cache_level' => ?int  which level that is (3 for L3, 2 for L2, …)
//
// The cache comes from /sys, not /proc/cpuinfo, because cpuinfo's `cache size`
// cannot be interpreted without knowing the vendor and cannot be scaled at all.
// On a Ryzen 5800X it reports 512 KB — the L2, shared by each hyperthread pair,
// while the cache that matters is 32 MB of L3 shared by all 16 threads.
// Multiplying by the processor count would double-count the pairs, and
// multiplying by physical cores would still report total L2 rather than the L3
// anyone means by "CPU cache". /sys carries the level and the sharing, so the
// last-level cache can be read directly.
//
// Only the core count is dependable. `cpu MHz` is absent on plenty of kernels
// and inside containers, and /sys may be missing entirely, so both figures are
// nullable and the hover simply omits what it does not have rather than
// printing a zero that looks like a measurement.
//
// Falls back to a single core when /proc is unreadable — open_basedir and
// non-Linux hosts both hide it — so the CPU gauge divides load by 1 rather than
// by a guess. That over-reports on a busy multi-core host, which is the safer
// direction to be wrong in for a warning gauge.

/** @return array{cores: int, mhz: float|null, cache: int|null, cache_level: int|null} */
function server_stats_cpuinfo(): array
{
    $info = ['cores' => 1, 'mhz' => null, 'cache' => null, 'cache_level' => null];

    $cpuinfo = @file_get_contents('/proc/cpuinfo');
    if ($cpuinfo === false) {
        return $info;
    }

    $cores = preg_match_all('/^processor\s*:/m', $cpuinfo);
    if ($cores > 0) {
        $info['cores'] = $cores;
    }

    if (preg_match('/^cpu MHz\s*:\s*([\d.]+)/m', $cpuinfo, $match) === 1) {
        $info['mhz'] = (float) $match[1];
    } elseif (preg_match('/@\s*([\d.]+)\s*GHz/i', $cpuinfo, $match) === 1) {
        // Intel and some AMD parts carry the rated clock in `model name` even
        // when the kernel reports no live frequency.
        $info['mhz'] = ((float) $match[1]) * 1000;
    }

    // The last-level cache, from the one source that says which level it is.
    // Read off cpu0: on a multi-socket machine that is one socket's LLC, which
    // is still the figure a per-core view wants.
    foreach (glob('/sys/devices/system/cpu/cpu0/cache/index*') ?: [] as $index) {
        $level = @file_get_contents($index.'/level');
        $size = @file_get_contents($index.'/size');
        if ($level === false || $size === false) {
            continue;
        }
        $level = intval(trim($level));
        if ($info['cache_level'] !== null && $level <= $info['cache_level']) {
            continue;
        }
        // Sizes come as "512K" or "32768K"; megabytes appear on larger parts.
        if (preg_match('/^(\d+)([KMG])$/i', trim($size), $match) !== 1) {
            continue;
        }
        $scale = ['K' => 1024, 'M' => 1048576, 'G' => 1073741824];
        $info['cache'] = intval($match[1]) * $scale[strtoupper($match[2])];
        $info['cache_level'] = $level;
    }

    return $info;
}
