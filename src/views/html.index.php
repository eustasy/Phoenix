<?php

declare(strict_types=1);

////	view_index_html
// Renders a normalized $index array as an HTML torrent index table.
// Columns: Title, File*, Hash, Trackers*, Webseeds*, Seeders, Leechers,
// Tracked Downloads, Health, Magnet — the starred meta columns appear only
// when $show_meta is set (mirroring the JSON/XML views' index_show_meta
// gating); the magnet link (built by public/index.php) renders either way.
// Health is the seeder share of the swarm (seeders / (seeders + leechers))
// as a percentage; an empty swarm renders as a dash.
// Returns HTML string. Caller is responsible for setting Content-Type header.

/** @param list<array{info_hash: string|null, name: string|null, size: int, downloads: int, seeders: int, leechers: int, peers: int, traffic: int, filename?: string|null, files?: list<array{path: string, length: int}>|null, trackers?: list<string>|null, webseeds?: list<string>|null, magnet?: string|null}> $index */
function view_index_html(array $index, bool $show_meta = false): string
{
    // One URL per line inside a cell; a dash when the torrent carries none.
    /** @param list<string>|null $urls */
    $url_list = static function (?array $urls): string {
        if (empty($urls)) {
            return '&mdash;';
        }

        return implode('<br>', array_map(
            static fn (string $url): string => htmlspecialchars($url),
            $urls,
        ));
    };

    $headers = $show_meta
        ? ['Title', 'File', 'Hash', 'Trackers', 'Webseeds', 'Seeders', 'Leechers', 'Tracked Downloads', 'Health', 'Magnet']
        : ['Title', 'Hash', 'Seeders', 'Leechers', 'Tracked Downloads', 'Health', 'Magnet'];

    $html = '<!DocType html><html><head><meta charset="UTF-8"><title>Torrent Index</title></head><body>'.
        '<table><thead><tr><th>'.implode('</th><th>', $headers).'</th></tr></thead><tbody>';

    foreach ($index as $torrent) {
        $swarm = $torrent['seeders'] + $torrent['leechers'];
        $health = $swarm === 0
            ? '&mdash;'
            : round($torrent['seeders'] / $swarm * 100).'%';

        $cells = [htmlspecialchars($torrent['name'] ?? '')];
        if ($show_meta) {
            $cells[] = htmlspecialchars($torrent['filename'] ?? '') ?: '&mdash;';
        }
        $cells[] = htmlspecialchars($torrent['info_hash'] ?? '');
        if ($show_meta) {
            $cells[] = $url_list($torrent['trackers'] ?? null);
            $cells[] = $url_list($torrent['webseeds'] ?? null);
        }
        $cells[] = (string) $torrent['seeders'];
        $cells[] = (string) $torrent['leechers'];
        $cells[] = (string) $torrent['downloads'];
        $cells[] = $health;

        // Magnet URIs join parameters with '&', so the href must be escaped.
        $magnet = $torrent['magnet'] ?? null;
        $cells[] = $magnet !== null
            ? '<a href="'.htmlspecialchars($magnet).'">magnet</a>'
            : '&mdash;';

        $html .= '<tr><td>'.implode('</td><td>', $cells).'</td></tr>';
    }

    return $html.'</tbody></table></body></html>';
}
