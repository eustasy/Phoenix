<?php

declare(strict_types=1);

////	stats_client_family
// Split a client label into its family and version — "Transmission 4.1.3.0"
// into ['Transmission', '4.1.3.0'].
//
// Only a tail that actually looks like a version is split off. "Unknown" must
// not become family "Unk" version "nown", a client whose name contains a space
// keeps it, and a label like "Tribler (versions >= 6.1.0)" is left whole
// because its tail is not a bare version number.
//
// Shared because the two client views need the same notion of a family from
// different sources: the live swarm derives labels from peer_id per request,
// while the events ledger stores whatever label was written at the time — and
// rows written before this tracker recorded versions carry the family alone.
// Normalising both through here is what makes them comparable.
//
// Returns ['family' => string, 'version' => string]; version is '' when the
// label carries none.

/** @return array{family: string, version: string} */
function stats_client_family(string $label): array
{
    $split = strrpos($label, ' ');
    if ($split === false) {
        return ['family' => $label, 'version' => ''];
    }

    $tail = substr($label, $split + 1);
    if ($tail === '' || preg_match('/^[0-9][0-9.]*$/', $tail) !== 1) {
        return ['family' => $label, 'version' => ''];
    }

    return ['family' => substr($label, 0, $split), 'version' => $tail];
}
