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

    public function testEmptyIndexProducesEmptyTable(): void
    {
        $html = view_index_html([]);
        $this->assertStringContainsString('<tbody></tbody>', $html);
    }

    public function testOutputStartsWithDoctype(): void
    {
        $html = view_index_html($this->fixture());
        $this->assertStringStartsWith('<!DocType html>', $html);
    }

    public function testBaseColumnsRenderWithoutMeta(): void
    {
        $html = view_index_html($this->fixture());

        foreach (['Title', 'Hash', 'Seeders', 'Leechers', 'Tracked Downloads', 'Health', 'Magnet'] as $header) {
            $this->assertStringContainsString('<th>'.$header.'</th>', $html);
        }
        $this->assertStringContainsString('<td>Test Torrent</td>', $html);
        $this->assertStringContainsString('<td>aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa</td>', $html);
        $this->assertStringContainsString('<td>2</td>', $html);
        $this->assertStringContainsString('<td>1</td>', $html);
        $this->assertStringContainsString('<td>7</td>', $html);
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

        foreach (['Title', 'File', 'Hash', 'Trackers', 'Webseeds', 'Seeders', 'Leechers', 'Tracked Downloads', 'Health', 'Magnet'] as $header) {
            $this->assertStringContainsString('<th>'.$header.'</th>', $html);
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
        // 2 seeders, 1 leecher -> 67%.
        $html = view_index_html($this->fixture());
        $this->assertStringContainsString('<td>67%</td>', $html);
    }

    public function testHealthIsZeroPercentWhenSeederless(): void
    {
        $html = view_index_html($this->fixture(['seeders' => 0, 'leechers' => 4]));
        $this->assertStringContainsString('<td>0%</td>', $html);
    }

    public function testHealthIsDashForEmptySwarm(): void
    {
        $html = view_index_html($this->fixture(['seeders' => 0, 'leechers' => 0]));
        $this->assertStringContainsString('<td>&mdash;</td>', $html);
    }

    public function testNameFilenameAndTrackersAreHtmlEscaped(): void
    {
        $html = view_index_html($this->fixture([
            'name' => '<script>alert(1)</script>',
            'filename' => '<b>.iso',
            'trackers' => ['https://x.example/announce?a=1&b=<2>'],
        ]), true);

        $this->assertStringNotContainsString('<script>', $html);
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
            '<a href="magnet:?xt=urn:btih:'.str_repeat('a', 40).'&amp;dn=Test">magnet</a>',
            $html,
        );
    }

    public function testMagnetCellIsDashWhenAbsent(): void
    {
        // Fixture rows carry no magnet key; 2s/1l keeps Health off the dash,
        // so with meta off the only dash cell is the Magnet column.
        $html = view_index_html($this->fixture());
        $this->assertStringContainsString('<td>&mdash;</td>', $html);
        $this->assertStringNotContainsString('<a href="magnet:', $html);
    }

    public function testMultipleTorrentsEachGetARow(): void
    {
        $index = $this->fixture();
        $index[] = $this->fixture([
            'info_hash' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            'name' => 'Second Torrent',
        ])[0];

        $html = view_index_html($index);
        $this->assertSame(2, substr_count($html, '<tr><td>'));
        $this->assertStringContainsString('Second Torrent', $html);
    }
}
