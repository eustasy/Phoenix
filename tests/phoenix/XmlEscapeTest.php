<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../src/functions/xml.escape.php';

class XmlEscapeTest extends TestCase
{
    public function testEscapesXmlMetacharacters(): void
    {
        $this->assertSame('a &lt;b&gt; &amp; c', xml_escape('a <b> & c'));
    }

    public function testEscapesQuotesForAttributeSafety(): void
    {
        // ENT_XML1 uses the named entities &quot; / &apos; (not numeric).
        $this->assertSame('&quot;x&quot; &apos;y&apos;', xml_escape('"x" \'y\''));
    }

    public function testLeavesPlainTextUnchanged(): void
    {
        $this->assertSame('plain text 123', xml_escape('plain text 123'));
    }

    public function testEmptyString(): void
    {
        $this->assertSame('', xml_escape(''));
    }

    public function testPreservesUnicode(): void
    {
        $this->assertSame('snowman ☃', xml_escape('snowman ☃'));
    }
}
