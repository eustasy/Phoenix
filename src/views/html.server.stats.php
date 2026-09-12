<?php

declare(strict_types=1);

////	view_server_stats_html
// Render the sidebar's server gauges: one row per metric, with the name on the
// left, the figure on the right, and a bar underneath. The row's title carries
// the detail — load across cores for CPU, used-of-total for the rest — so the
// sidebar stays a glance and the specifics are a hover away.
//
// Every metric gets a row whether or not it could be read. A machine with swap
// switched off shows "Off", and a host that hides /proc shows "n/a": both are
// worth knowing, and a gauge that silently disappears reads as a bug rather
// than as the deliberate configuration it usually is.
//
// The bar clamps at 100% while the label does not. CPU is load over cores, so
// figures above 100 are real and mean work is queuing — the number says so even
// though the bar has run out of room.
//
// Each metric carries its own colour, so a glance tells the rows apart without
// reading them. Pressure overrides that: at 75% a bar turns warning, at 90%
// danger, because at that point which metric it is matters less than that it is
// nearly full.
//
// Returns HTML string, or '' when there is nothing to show at all.

/** @param array<string, array{available: bool, percent: int|null, detail: string, note: string}> $stats */
function view_server_stats_html(array $stats): string
{
    if ($stats === []) {
        return '';
    }

    $labels = ['cpu' => 'CPU', 'memory' => 'Memory', 'swap' => 'Swap', 'disk' => 'Disk'];

    $rows = '';
    foreach ($labels as $key => $label) {
        if (! isset($stats[$key])) {
            continue;
        }
        $stat = $stats[$key];
        $percent = $stat['percent'];

        // The figure shown on the right: a percentage, or the note standing in
        // for one ("Off", "n/a").
        $value = $percent === null
            ? '<span class="dim">'.htmlspecialchars($stat['note'], ENT_QUOTES, 'UTF-8').'</span>'
            : $percent.'%';

        $width = $percent === null ? 0 : max(0, min(100, $percent));
        $level = '';
        if ($percent !== null && $percent >= 90) {
            $level = ' is-critical';
        } elseif ($percent !== null && $percent >= 75) {
            $level = ' is-warning';
        }

        $rows .= '<div class="ph-srv ph-srv--'.$key.$level.'" title="'.htmlspecialchars($label.' — '.$stat['detail'], ENT_QUOTES, 'UTF-8').'">'.
            '<div class="ph-srv-head"><span class="ph-srv-name">'.$label.'</span>'.
            '<span class="ph-srv-val mono">'.$value.'</span></div>'.
            '<div class="ph-srv-bar"><span style="width:'.$width.'%"></span></div>'.
            '</div>';
    }

    if ($rows === '') {
        return '';
    }

    return '<div class="ph-srv-group" aria-label="Server resources">'.$rows.'</div>';
}
