<?php

declare(strict_types=1);

////	view_health_html
// A swarm-health cell: the seeder share of the swarm (seeders / seeders +
// leechers) as a coloured mini-bar plus its percentage. Green at >= 50%, amber
// at >= 25%, red below; an empty swarm has no health and renders as a dash.
// The data-sort attribute (caller wraps this in the <td>) keys column sorting.
// Returns an HTML fragment for use inside a table cell.

function view_health_html(int $seeders, int $leechers): string
{
    $swarm = $seeders + $leechers;
    if ($swarm === 0) {
        return '<span class="dim">&mdash;</span>';
    }

    $percent = (int) round($seeders / $swarm * 100);
    $level = $percent >= 50 ? 'good' : ($percent >= 25 ? 'warn' : 'bad');

    return '<span class="health health--'.$level.'">'.
        '<span class="health-bar"><span class="health-fill" style="width:'.$percent.'%"></span></span>'.
        '<span class="health-num">'.$percent.'%</span>'.
        '</span>';
}
