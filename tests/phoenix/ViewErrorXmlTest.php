<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ViewErrorXmlTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/xml.error.php';
    }

    public function testRendersWrappedXmlDocument(): void
    {
        $this->assertSame(
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><error>oops</error>',
            view_error_xml('oops'),
        );
    }

    public function testHandlesEmptyMessage(): void
    {
        $this->assertSame(
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><error></error>',
            view_error_xml(''),
        );
    }

    public function testEscapesXmlSpecialCharacters(): void
    {
        $out = view_error_xml('a < b & c > d');
        $doc = simplexml_load_string($out);
        $this->assertNotFalse($doc);
        $this->assertSame('a < b & c > d', (string) $doc);
    }

    public function testRoundTripsArbitraryMessages(): void
    {
        // Anything we hand the renderer must come back identical when parsed.
        foreach (['simple', '<script>', '"quoted" & \'apostrophe\'', 'unicode ☃ snowman'] as $msg) {
            $doc = simplexml_load_string(view_error_xml($msg));
            $this->assertNotFalse($doc);
            $this->assertSame($msg, (string) $doc);
        }
    }

    public function testNestsRetryInChild(): void
    {
        $out = view_error_xml('nope', 'never');
        $this->assertSame(
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><error>nope<retry_in>never</retry_in></error>',
            $out,
        );
        // Single root preserved; message is the element text, retry_in a child.
        $doc = simplexml_load_string($out);
        $this->assertNotFalse($doc);
        $this->assertSame('nope', (string) $doc);
        $this->assertSame('never', (string) $doc->retry_in);
    }

    public function testNumericRetryInChild(): void
    {
        $doc = simplexml_load_string(view_error_xml('nope', 90));
        $this->assertNotFalse($doc);
        $this->assertSame('90', (string) $doc->retry_in);
    }

    public function testOmitsRetryInByDefault(): void
    {
        $this->assertStringNotContainsString('retry_in', view_error_xml('nope'));
    }

}
