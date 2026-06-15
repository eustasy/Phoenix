<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class ViewIndexHtmlTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/html.index.php';
    }

    /**
     * @param array<string, mixed> $overrides
     * @return list<array<string, mixed>>
     */
    private function fixture(array $overrides = []): array
    {
        return [array_merge([
            'info_hash' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'name' => 'Test Torrent',
            'size' => 1024,
            'downloads' => 7,
            'seeders' => 2,
            'leechers' => 1,
            'peers' => 3,
            'traffic' => 7168,
            'filename' => 'test.iso',
            'files' => [['path' => 'test.iso', 'length' => 1024]],
            'trackers' => ['https://tracker.example.com/announce'],
            'webseeds' => null,
        ], $overrides)];
    }

    public function testEmptyIndexProducesEmptyTableBody(): void
    {
        $html = view_index_html([]);
        $this->assertStringContainsString('<tbody></tbody>', $html);
        // The empty-state panel ships with the table, hidden until JS filters.
        $this->assertStringContainsString('class="ph-empty"', $html);
    }

    public function testOutputIsAFullDocumentWithChrome(): void
    {
        $html = view_index_html($this->fixture());
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<h1>Torrent Index</h1>', $html);
        // Public chrome + bundled assets.
        $this->assertStringContainsString('class="ph-public"', $html);
        $this->assertStringContainsString('/assets/ds/all.css', $html);
        $this->assertStringContainsString('/assets/phoenix.css', $html);
    }

    public function testBaseColumnsRenderWithoutMeta(): void
    {
        $html = view_index_html($this->fixture());

        // Plain (non-sortable) headers render as bare <th>; sortable ones carry
        // classes, so assert their labels appear.
        foreach (['>Title ', '<th>Hash</th>', '>Seeders ', '>Leechers</th>', '>Downloads</th>', '>Health</th>', '>Magnet</th>'] as $header) {
            $this->assertStringContainsString($header, $html);
        }
        $this->assertStringContainsString('<span class="ph-name">Test Torrent</span>', $html);
        // The hash cell truncates and carries the full hash for click-to-copy.
        $this->assertStringContainsString('<span class="hash-text">aaaaaaaaaaaa</span>', $html);
        $this->assertStringContainsString('data-hash="aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"', $html);
        $this->assertStringContainsString('<td class="table-col-numeric">2</td>', $html);
        $this->assertStringContainsString('<td class="table-col-numeric">1</td>', $html);
        $this->assertStringContainsString('<td class="table-col-numeric">7</td>', $html);
    }

    public function testLargeCountsAreThousandsFormatted(): void
    {
        $html = view_index_html($this->fixture(['downloads' => 1337]));
        $this->assertStringContainsString('<td class="table-col-numeric">1,337</td>', $html);
    }

    public function testMetaColumnsHiddenByDefault(): void
    {
        $html = view_index_html($this->fixture());

        $this->assertStringNotContainsString('<th>File</th>', $html);
        $this->assertStringNotContainsString('<th>Trackers</th>', $html);
        $this->assertStringNotContainsString('<th>Webseeds</th>', $html);
        $this->assertStringNotContainsString('test.iso', $html);
        $this->assertStringNotContainsString('tracker.example.com', $html);
    }

    public function testMetaColumnsRenderWhenEnabled(): void
    {
        $html = view_index_html($this->fixture(), true);

        foreach (['<th>File</th>', '<th>Trackers</th>', '<th>Webseeds</th>'] as $header) {
            $this->assertStringContainsString($header, $html);
        }
        $this->assertStringContainsString('<td>test.iso</td>', $html);
        $this->assertStringContainsString('https://tracker.example.com/announce', $html);
        // Null webseeds render as a dash.
        $this->assertStringContainsString('<td>&mdash;</td>', $html);
    }

    public function testMultipleTrackersJoinWithLineBreaks(): void
    {
        $html = view_index_html($this->fixture([
            'trackers' => ['https://a.example/announce', 'https://b.example/announce'],
        ]), true);

        $this->assertStringContainsString(
            'https://a.example/announce<br>https://b.example/announce',
            $html,
        );
    }

    public function testHealthIsSeederShareOfSwarm(): void
    {
        // 2 seeders, 1 leecher -> 67%, healthy.
        $html = view_index_html($this->fixture());
        $this->assertStringContainsString('health health--good', $html);
        $this->assertStringContainsString('<span class="health-num">67%</span>', $html);
        $this->assertStringContainsString('data-sort="67"', $html);
    }

    public function testHealthIsZeroPercentWhenSeederless(): void
    {
        $html = view_index_html($this->fixture(['seeders' => 0, 'leechers' => 4]));
        $this->assertStringContainsString('health health--bad', $html);
        $this->assertStringContainsString('<span class="health-num">0%</span>', $html);
    }

    public function testHealthIsDashForEmptySwarm(): void
    {
        $html = view_index_html($this->fixture(['seeders' => 0, 'leechers' => 0]));
        $this->assertStringContainsString('<td data-sort="-1"><span class="dim">&mdash;</span></td>', $html);
    }

    public function testNameFilenameAndTrackersAreHtmlEscaped(): void
    {
        $html = view_index_html($this->fixture([
            'name' => '<script>alert(1)</script>',
            'filename' => '<b>.iso',
            'trackers' => ['https://x.example/announce?a=1&b=<2>'],
        ]), true);

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<b>.iso', $html);
        $this->assertStringContainsString('&lt;b&gt;.iso', $html);
        $this->assertStringNotContainsString('<2>', $html);
        $this->assertStringContainsString('&amp;b=&lt;2&gt;', $html);
    }

    public function testMagnetRendersAsEscapedLink(): void
    {
        $html = view_index_html($this->fixture([
            'magnet' => 'magnet:?xt=urn:btih:'.str_repeat('a', 40).'&dn=Test',
        ]));

        $this->assertStringContainsString(
            'href="magnet:?xt=urn:btih:'.str_repeat('a', 40).'&amp;dn=Test"',
            $html,
        );
        $this->assertStringContainsString('class="magnet-link"', $html);
    }

    public function testMagnetCellIsDashWhenAbsent(): void
    {
        // Fixture rows carry no magnet key; 2s/1l keeps Health off the dash,
        // so the Magnet column is the dash.
        $html = view_index_html($this->fixture());
        $this->assertStringContainsString('<td class="tar">&mdash;</td>', $html);
        $this->assertStringNotContainsString('href="magnet:', $html);
    }

    public function testMultipleTorrentsEachGetARow(): void
    {
        $index = $this->fixture();
        $index[] = $this->fixture([
            'info_hash' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            'name' => 'Second Torrent',
        ])[0];

        $html = view_index_html($index);
        $this->assertSame(2, substr_count($html, 'class="ph-name"'));
        $this->assertStringContainsString('Second Torrent', $html);
        // The count header reflects the row total.
        $this->assertStringContainsString('>2 torrents</b>', $html);
    }
}
