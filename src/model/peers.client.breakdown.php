<?php

declare(strict_types=1);

////	peers_client_breakdown
// Active peers grouped by client family, then by version — the shape behind the
// dashboard's stacked client chart, where one bar per family is divided into
// its versions.
//
// Built on peers_client_counts() rather than a second query: that already
// aggregates on the peer_id prefix in SQL and labels in PHP, so this only has
// to regroup a few dozen labels.
//
// stats_client_detect() returns a label like "Transmission 4.1.3.0" — family
// and version separated by the last space. A label with no version part (an
// unrecognised client, or plain "Unknown") becomes a family with a single ''
// version, so it still charts as one solid bar rather than disappearing.
//
// Families are ordered by total peers, highest first; versions within a family
// likewise. Returns ['Transmission' => ['4.1.3.0' => 49, '3.0.0.0' => 33], …].

/**
 * @param PhoenixSettings $settings
 * @return array<string, array<string, int>>
 */
function peers_client_breakdown(mysqli $connection, array $settings): array
{
    require_once __DIR__.'/peers.client.counts.php';

    $families = [];
    foreach (peers_client_counts($connection, $settings) as $label => $count) {
        $label = (string) $label;
        $family = $label;
        $version = '';

        $split = strrpos($label, ' ');
        // Only treat the tail as a version when it actually looks like one —
        // "Unknown" must not become family "Unk" version "nown", and a client
        // whose name contains a space keeps it.
        if ($split !== false) {
            $tail = substr($label, $split + 1);
            if ($tail !== '' && preg_match('/^[0-9][0-9.]*$/', $tail) === 1) {
                $family = substr($label, 0, $split);
                $version = $tail;
            }
        }

        $families[$family][$version] = ($families[$family][$version] ?? 0) + $count;
    }

    // Biggest family first, and biggest version within each.
    uasort($families, static fn (array $a, array $b): int => array_sum($b) <=> array_sum($a));
    foreach ($families as &$versions) {
        arsort($versions);
    }
    unset($versions);

    return $families;
}
