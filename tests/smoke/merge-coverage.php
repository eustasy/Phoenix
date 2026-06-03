<?php

declare(strict_types=1);

// Merge the per-request PCOV dumps written by coverage-prepend.php into a single
// Clover report for upload to qlty (which unions it with the unit-test clover).
//
// Usage: php tests/smoke/merge-coverage.php <dump-dir> <output-clover.xml>
//
// PCOV dumps are JSON maps of  file => { line => hit-count }  (cumulative, so
// later dumps superset earlier ones — we union/max anyway). Only src/ and
// public/ files are kept; everything else is noise.

$dumpDir = $argv[1] ?? '';
$outFile = $argv[2] ?? '';
if ($dumpDir === '' || $outFile === '') {
    fwrite(STDERR, "usage: merge-coverage.php <dump-dir> <output-clover.xml>\n");
    exit(1);
}

$root = dirname(__DIR__, 2);
$keep = [$root.'/src/', $root.'/public/', $root.'/bin/'];

/** @var array<string, array<int, int>> $merged */
$merged = [];
foreach (glob($dumpDir.'/*.json') ?: [] as $dump) {
    $data = json_decode((string) file_get_contents($dump), true);
    if (! is_array($data)) {
        continue;
    }
    foreach ($data as $file => $lines) {
        if (! is_string($file) || ! is_array($lines)) {
            continue;
        }
        $real = realpath($file);
        if ($real === false) {
            continue;
        }
        $in = false;
        foreach ($keep as $prefix) {
            if (str_starts_with($real, $prefix)) {
                $in = true;
                break;
            }
        }
        if (! $in) {
            continue;
        }
        foreach ($lines as $line => $count) {
            $line = (int) $line;
            $count = (int) $count;
            $merged[$real][$line] = max($merged[$real][$line] ?? 0, $count);
        }
    }
}

ksort($merged);
$ts = time();
$lines = ['<?xml version="1.0" encoding="UTF-8"?>'];
$lines[] = '<coverage generated="'.$ts.'">';
$lines[] = '  <project timestamp="'.$ts.'">';
foreach ($merged as $file => $hits) {
    ksort($hits);
    $covered = 0;
    $lines[] = '    <file name="'.htmlspecialchars($file, ENT_XML1 | ENT_QUOTES, 'UTF-8').'">';
    foreach ($hits as $line => $count) {
        if ($count > 0) {
            $covered++;
        }
        $lines[] = '      <line num="'.$line.'" type="stmt" count="'.$count.'"/>';
    }
    $lines[] = '      <metrics statements="'.count($hits).'" coveredstatements="'.$covered.'"/>';
    $lines[] = '    </file>';
}
$lines[] = '  </project>';
$lines[] = '</coverage>';

if (! is_dir(dirname($outFile))) {
    @mkdir(dirname($outFile), 0o777, true);
}
file_put_contents($outFile, implode("\n", $lines)."\n");
fwrite(STDOUT, 'Wrote '.$outFile.' ('.count($merged).' files covered)'.PHP_EOL);
