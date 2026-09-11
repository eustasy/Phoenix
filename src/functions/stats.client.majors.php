<?php

declare(strict_types=1);

////	stats_client_majors
// Regroup one family's versions by major release — ['4.1.3.0' => 49,
// '4.0.6' => 12, '3.0.0' => 7] into ['4' => 61, '3' => 7], each keeping the
// exact versions behind it.
//
// Full version strings are too fine to group by. Clients ship point releases
// constantly, so a swarm of a few hundred Transmission peers spreads across a
// dozen build numbers that differ by nothing anyone is asking about: the
// question is how much of the swarm is on 4 versus 3, not how many are on
// 4.1.3.0 versus 4.1.2.0. Charted by full version, each bar fragments into
// slivers and the stack stops being readable.
//
// The major is the leading numeric component, so '4.1.3.0' and '4.1' both give
// '4'. A version that does not start with a digit is not a version number this
// can reason about and is kept whole as its own group, rather than being
// silently merged into something it does not belong to. The '' version — a
// label that carried none — stays '', and charts as one solid bar.
//
// Groups come back ordered by total, largest first, and the versions inside
// each likewise, so a caller can render them in order without re-sorting.
//
// Returns ['4' => ['total' => 61, 'versions' => ['4.1.3.0' => 49, …]], …].
//
// Note the key type: PHP turns a numeric-looking array key into an int, so the
// major '4' is read back as int 4 while a non-numeric group keeps its string.
// Compare with a cast, never a strict === against a string, or the comparison
// silently never matches.

/**
 * @param array<array-key, int> $versions version string => count
 * @return array<array-key, array{total: int, versions: array<array-key, int>}>
 */
function stats_client_majors(array $versions): array
{
    $majors = [];
    foreach ($versions as $version => $count) {
        $version = (string) $version;

        if ($version === '' || preg_match('/^([0-9]+)/', $version, $m) !== 1) {
            $major = $version;
        } else {
            $major = $m[1];
        }

        if (! isset($majors[$major])) {
            $majors[$major] = ['total' => 0, 'versions' => []];
        }
        $majors[$major]['total'] += $count;
        $majors[$major]['versions'][$version] = ($majors[$major]['versions'][$version] ?? 0) + $count;
    }

    uasort($majors, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);
    foreach ($majors as &$group) {
        arsort($group['versions']);
    }
    unset($group);

    return $majors;
}
