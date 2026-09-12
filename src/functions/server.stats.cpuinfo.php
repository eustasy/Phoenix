<?php

declare(strict_types=1);

////	server_stats_cpuinfo
// Read what /proc/cpuinfo will say about the processor, for the sidebar's CPU
// gauge and its hover.
//
// Returns:
//   'cores' => int    always at least 1
//   'mhz'   => ?float clock speed, null when the kernel does not report one
//   'cache' => ?int   cache size in bytes, null when not reported
//
// Only the core count is dependable. `cpu MHz` is absent on plenty of kernels
// and inside containers, and `cache size` with it — a DigitalOcean droplet
// reports neither, and gives `model name: DO-Regular` rather than a part
// number. Both are therefore nullable and the hover simply omits what it does
// not have, rather than printing a zero that looks like a measurement.
//
// Falls back to a single core when /proc is unreadable — open_basedir and
// non-Linux hosts both hide it — so the CPU gauge divides load by 1 rather than
// by a guess. That over-reports on a busy multi-core host, which is the safer
// direction to be wrong in for a warning gauge.

/** @return array{cores: int, mhz: float|null, cache: int|null} */
function server_stats_cpuinfo(): array
{
    $info = ['cores' => 1, 'mhz' => null, 'cache' => null];

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

    // Reported per core, and in kB unless a kernel says otherwise.
    if (preg_match('/^cache size\s*:\s*(\d+)\s*KB/mi', $cpuinfo, $match) === 1) {
        $info['cache'] = intval($match[1]) * 1024;
    }

    return $info;
}
