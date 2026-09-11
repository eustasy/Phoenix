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
// Groups come back ordered by VERSION, newest first, and the exact versions
// inside each likewise, so a caller can render them in order without
// re-sorting. Not by count: the stacked bars are a split of one family across
// its releases, and a reader following a version across families wants it in
// the same place each time, rather than shuffling with whichever release
// happens to be most popular in that family today. Newest leads because the
// chart's first slot carries the strongest shade, and "what is current" is the
// question the chart is usually asked.
//
// A major that is not a version number sorts after every numbered one, and the
// '' group — a label that carried no version at all — sorts last, so neither
// pushes real releases out of order.
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

    // Newest version first, with anything that is not a version number pushed
    // behind the ones that are, and the versionless group last of all. uksort
    // because the key IS the version; it arrives as an int for a numeric major,
    // hence the casts. version_compare handles the dotted forms ('4.10' after
    // '4.9', which a string sort gets backwards).
    $by_version = static function (int|string $a, int|string $b): int {
        $a = (string) $a;
        $b = (string) $b;

        $rank = static fn (string $v): int => match (true) {
            $v === '' => 2,
            preg_match('/^[0-9]/', $v) === 1 => 0,
            default => 1,
        };

        return $rank($a) <=> $rank($b)
            ?: version_compare($b, $a)
            ?: strcmp($a, $b);
    };

    uksort($majors, $by_version);
    foreach ($majors as &$group) {
        uksort($group['versions'], $by_version);
    }
    unset($group);

    return $majors;
}
