<?php

declare(strict_types=1);

////	peer_select_strategy
// Determines the SQL WHERE extension and ORDER BY clause for peer selection
// based on the announcing peer's progress and the current swarm size.
// Returns array('where' => string, 'order' => string).
//
// Cases:
//  - left == 0   : announcer is seeding; only show leechers, prioritise nearest-to-done.
//  - left > downloaded : likely <50% done; only show seeders + likely-seeders, ordered by recency.
//  - left > 0 (else)   : likely >=50% done; order by progress, randomise within tiers if swarm is large.
//  - left < 0    : state unknown (left not reported); order by recency.
/**
 * @param array<string, mixed> $peer
 * @param array<string, mixed> $settings
 * @return array{where: string, order: string}
 */
function peer_select_strategy(array $peer, int $complete, int $incomplete, array $settings): array
{
    if ($peer['left'] == 0) {
        return [
            'where' => ' AND `state`=\'0\'',
            'order' => ' ORDER BY `left` ASC, `updated` DESC',
        ];
    }
    if ($peer['left'] > 0 && $peer['left'] > $peer['downloaded']) {
        return [
            'where' => ' AND (`state`=\'1\' OR `downloaded` > `left`)',
            'order' => ' ORDER BY `updated` DESC',
        ];
    }
    if ($peer['left'] > 0) {
        $randomise = $settings['random_peers']
            && ($complete + $incomplete) > intval((string) $settings['random_limit']);

        return [
            'where' => '',
            'order' => $randomise
                ? ' ORDER BY `left` ASC, RAND()'
                : ' ORDER BY `left` ASC, `updated` DESC',
        ];
    }

    return [
        'where' => '',
        'order' => ' ORDER BY `updated` DESC',
    ];
}
