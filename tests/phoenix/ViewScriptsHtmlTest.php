<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../src/functions/cdn.assets.php';
require_once __DIR__.'/../../src/views/html.scripts.php';

final class ViewScriptsHtmlTest extends TestCase
{
    public function testAlwaysLoadsLucideAndTheSharedHelpers(): void
    {
        $html = view_scripts_html();

        $this->assertStringContainsString(cdn_assets()['lucide']['url'], $html);
        // app.js renders the icons, so it has to come after the library.
        $this->assertLessThan(
            strpos($html, '/assets/app.js'),
            strpos($html, cdn_assets()['lucide']['url']),
        );
    }

    public function testKnownCdnSourcesCarryTheirIntegrityHash(): void
    {
        $chart = cdn_assets()['chart'];
        $html = view_scripts_html('', [$chart['url']]);

        $this->assertStringContainsString(
            '<script src="'.$chart['url'].'" integrity="'.$chart['integrity'].'" crossorigin="anonymous"></script>',
            $html,
        );
    }

    public function testLocalSourcesGetNoIntegrity(): void
    {
        // Same-origin assets are not subresources from another host; an
        // integrity attribute there would only be a maintenance burden.
        $html = view_scripts_html('', ['/assets/tables.js']);

        $this->assertStringContainsString('<script src="/assets/tables.js"></script>', $html);
    }

    public function testUnknownSourceIsEmittedWithoutInventingAHash(): void
    {
        $html = view_scripts_html('', ['https://cdn.jsdelivr.net/npm/nothing@1.0.0/x.js']);

        $this->assertStringContainsString('src="https://cdn.jsdelivr.net/npm/nothing@1.0.0/x.js"', $html);
        $this->assertSame(1, substr_count($html, 'integrity='), 'only lucide should carry one');
    }

    public function testInlineScriptOnlyWhenThereIsSomethingToInline(): void
    {
        $this->assertStringNotContainsString('<script>', view_scripts_html());
        $this->assertStringContainsString('<script>var X = 1;</script>', view_scripts_html('var X = 1;'));
    }

    public function testSourcesAreAttributeEscaped(): void
    {
        $html = view_scripts_html('', ['/x.js?a=1&b=2"><script>alert(1)</script>']);

        $this->assertStringNotContainsString('"><script>alert(1)', $html);
        $this->assertStringContainsString('&amp;', $html);
    }
}
