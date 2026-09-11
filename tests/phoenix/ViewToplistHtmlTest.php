<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../src/views/html.toplist.php';

final class ViewToplistHtmlTest extends TestCase
{
    public function testRendersRankedRowsInOrder(): void
    {
        $html = view_toplist_html('Most seeded', [
            ['label' => 'Alpha', 'value' => '50 seeders', 'bar' => 100],
            ['label' => 'Beta', 'value' => '20 seeders', 'bar' => 40],
        ]);

        $this->assertStringContainsString('<h3>Most seeded</h3>', $html);
        // Ranks are 1-based and follow the order given, not the values.
        $this->assertStringContainsString('<span class="geo-rank">1</span>', $html);
        $this->assertStringContainsString('<span class="geo-rank">2</span>', $html);
        $this->assertLessThan(strpos($html, 'Beta'), strpos($html, 'Alpha'));
    }

    public function testBarWidthIsClampedToAPercentage(): void
    {
        // The bar is a proportion of the card's leader; a caller's arithmetic
        // must never escape the element.
        $html = view_toplist_html('T', [
            ['label' => 'over', 'value' => '1', 'bar' => 250],
            ['label' => 'under', 'value' => '1', 'bar' => -30],
        ]);

        $this->assertStringContainsString('width:100%', $html);
        $this->assertStringContainsString('width:0%', $html);
        $this->assertStringNotContainsString('width:250%', $html);
    }

    public function testRowLinksOnlyWhenGivenAnHref(): void
    {
        $linked = view_toplist_html('T', [['label' => 'A', 'value' => '1', 'href' => '?page=peers']]);
        $plain = view_toplist_html('T', [['label' => 'A', 'value' => '1']]);

        $this->assertStringContainsString('<a class="nm" href="?page=peers"', $linked);
        $this->assertStringContainsString('<span class="nm"', $plain);
        $this->assertStringNotContainsString('<a class="nm"', $plain);
    }

    public function testRowTitleBecomesATooltipAndDoesNotClobberTheCardTitle(): void
    {
        // Regression: the row's title once overwrote the card's own heading,
        // because both were held in a variable called $title.
        $html = view_toplist_html('Most seeded', [
            ['label' => 'A', 'value' => '1', 'title' => "hash\nfile.iso"],
        ]);

        $this->assertStringContainsString('<h3>Most seeded</h3>', $html);
        $this->assertStringContainsString('title="hash'."\n".'file.iso"', $html);
    }

    public function testEmptyRowsRenderTheCardWithItsEmptyNotice(): void
    {
        // The dashboard keeps its grid alignment rather than dropping a card.
        $html = view_toplist_html('Top seeders', [], 'var(--color-action)', null, 'No peers yet.');

        $this->assertStringContainsString('<h3>Top seeders</h3>', $html);
        $this->assertStringContainsString('No peers yet.', $html);
        $this->assertStringNotContainsString('geo-rank', $html);
    }

    public function testFooterLinkIsOptional(): void
    {
        $rows = [['label' => 'A', 'value' => '1']];

        $with = view_toplist_html('T', $rows, 'var(--color-action)', ['label' => 'All peers', 'href' => '?page=peers']);
        $without = view_toplist_html('T', $rows);

        $this->assertStringContainsString('ph-toplist-more', $with);
        $this->assertStringContainsString('>All peers</a>', $with);
        $this->assertStringNotContainsString('ph-toplist-more', $without);
    }

    public function testEscapesEveryCallerSuppliedString(): void
    {
        $html = view_toplist_html('<b>T</b>', [[
            'label' => '<script>alert(1)</script>',
            'value' => '<em>v</em>',
            'title' => '"quoted"',
        ]], '<accent>');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<b>T</b>', $html);
        $this->assertStringNotContainsString('<em>v</em>', $html);
        $this->assertStringNotContainsString('<accent>', $html);
    }
}
