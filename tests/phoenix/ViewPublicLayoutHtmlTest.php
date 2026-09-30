<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ViewPublicLayoutHtmlTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/html.public.layout.php';
    }

    public function testDefaultsToPhoenixBranding(): void
    {
        $html = view_public_layout_html('Index', '<p>body</p>', 'index');
        $this->assertStringContainsString('<link rel="icon" href="/assets/phoenix-mark.svg">', $html);
        $this->assertStringContainsString('class="ph-mark"', $html);
        $this->assertStringContainsString('<span class="ph-wordmark">Phoenix</span>', $html);
    }

    public function testEmptyBrandValuesFallBackToPhoenix(): void
    {
        // The settings default to '', which means "Phoenix's own", not "none".
        $html = view_public_layout_html('Index', '', 'index', '', false, '', '', [], [
            'favicon' => '',
            'mark' => '',
            'wordmark' => '',
        ]);
        $this->assertStringContainsString('<link rel="icon" href="/assets/phoenix-mark.svg">', $html);
        $this->assertStringContainsString('class="ph-mark"', $html);
        $this->assertStringContainsString('<span class="ph-wordmark">Phoenix</span>', $html);
    }

    public function testCustomFaviconReplacesPhoenixMark(): void
    {
        $html = view_public_layout_html('Index', '', 'index', '', false, '', '', [], ['favicon' => '/assets/ashrise.ico']);
        $this->assertStringContainsString('<link rel="icon" href="/assets/ashrise.ico">', $html);
        $this->assertStringNotContainsString('phoenix-mark.svg', $html);
    }

    public function testCustomMarkReplacesInlineSvg(): void
    {
        // Decorative beside the wordmark, which already names the link.
        $html = view_public_layout_html('Index', '', 'index', '', false, '', '', [], ['mark' => '/assets/ashrise.svg']);
        $this->assertStringContainsString('<img src="/assets/ashrise.svg" alt="">', $html);
        $this->assertStringNotContainsString('class="ph-mark"', $html);
    }

    public function testCustomWordmarkReplacesName(): void
    {
        $html = view_public_layout_html('Index', '', 'index', '', false, '', '', [], ['wordmark' => 'Ashrise']);
        $this->assertStringContainsString('<span class="ph-wordmark">Ashrise</span>', $html);
    }

    public function testBrandValuesAreEscaped(): void
    {
        $html = view_public_layout_html('Index', '', 'index', '', false, '', '', [], [
            'favicon' => '/x.ico" onload="alert(1)',
            'mark' => '/x.svg" onerror="alert(1)',
            'wordmark' => '<b>A&B</b>',
        ]);
        $this->assertStringNotContainsString('" onload="', $html);
        $this->assertStringNotContainsString('" onerror="', $html);
        $this->assertStringContainsString('<span class="ph-wordmark">&lt;b&gt;A&amp;B&lt;/b&gt;</span>', $html);
    }
}
