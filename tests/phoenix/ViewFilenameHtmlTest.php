<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../src/views/html.filename.php';

final class ViewFilenameHtmlTest extends TestCase
{
    public function testShortFilenameRendersWithoutATooltip(): void
    {
        // A title that repeats a value the reader can already see tells them
        // nothing. The longest filename on a real tracker sits well inside the
        // column, so this is the common case.
        $html = view_filename_html('elementaryos-8.0-stable-amd64.20250902rc.iso');

        $this->assertStringContainsString('ph-file', $html);
        $this->assertStringNotContainsString('title=', $html);
        $this->assertStringNotContainsString('<abbr', $html);
    }

    public function testFilenameThatExactlyFitsGetsNoTooltip(): void
    {
        $html = view_filename_html(str_repeat('x', 46));

        $this->assertStringNotContainsString('title=', $html);
    }

    public function testLongerFilenameCarriesTheFullValueOnHover(): void
    {
        // One character past the column width, the cell is cut — and the
        // tooltip becomes the only way to read the rest.
        $name = str_repeat('x', 47);
        $html = view_filename_html($name);

        $this->assertStringContainsString('<abbr', $html);
        $this->assertStringContainsString('title="'.$name.'"', $html);
        // ph-plain drops the <abbr> dotted underline, which would otherwise
        // announce the filename as an abbreviation.
        $this->assertStringContainsString('ph-plain', $html);
    }

    public function testAbsentFilenameRendersTheCallersEmptyStyle(): void
    {
        $this->assertStringContainsString('muted', view_filename_html(null));
        $this->assertStringContainsString('dim', view_filename_html(null, 'dim'));
        $this->assertStringContainsString('&mdash;', view_filename_html(''));
    }

    public function testEscapesBothTheCellAndTheTooltip(): void
    {
        $html = view_filename_html(str_repeat('a', 40).'<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $html);
        // Once in the visible text, once in the title attribute.
        $this->assertSame(2, substr_count($html, '&lt;script&gt;'));
    }

    public function testCountsCharactersNotBytes(): void
    {
        // 47 characters of two-byte UTF-8 is over the threshold; 47 bytes is
        // not, so a byte count would drop the tooltip the reader needs.
        $this->assertStringContainsString('title=', view_filename_html(str_repeat('é', 47)));
        $this->assertStringNotContainsString('title=', view_filename_html(str_repeat('é', 46)));
    }
}
