<?php

declare(strict_types=1);

////	stats_client_chart
// Shape a family => version => count breakdown into the payload the client
// chart (assets/_clients.js) draws: [[family, [[major, count], …]], …], each
// family's versions grouped by major as stats_client_majors() orders them.
//
// Lists of pairs rather than objects, because the order is the point and a
// JSON object does not keep it. JavaScript enumerates integer-like keys first,
// in ascending numeric order, ahead of every other key — so a client family
// named "53" jumped to the top of a chart sorted by count, and majors "3" and
// "4" came out oldest first, with the versionless '' remainder after them,
// instead of in the order stats_client_majors() sorted them into. A JSON array
// keeps its order.
//
// Families stay in the order given. Names and majors are cast back to strings,
// since PHP turns a numeric-looking array key into an int and the chart labels
// them as text.
//
// Shared by the dashboard and the Clients page, which draw the same chart.
//
// Returns [['Transmission', [['4', 61], ['3', 7]]], ['53', [['', 2]]]].

/**
 * @param array<array-key, array<array-key, int>> $families family => version => count
 * @return list<array{0: string, 1: list<array{0: string, 1: int}>}>
 */
function stats_client_chart(array $families): array
{
    require_once __DIR__.'/stats.client.majors.php';

    $chart = [];
    foreach ($families as $family => $versions) {
        $majors = [];
        foreach (stats_client_majors($versions) as $major => $group) {
            $majors[] = [(string) $major, $group['total']];
        }
        $chart[] = [(string) $family, $majors];
    }

    return $chart;
}
