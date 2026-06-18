<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class ViewScrapeXmlTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/xml.scrape.php';
    }

    /** @return array<string, array<string, int|string>> */
    private function fixture(): array
    {
        return [
            'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' => [
                'info_hash' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                'seeders' => 2,
                'leechers' => 1,
                'peers' => 3,
                'size' => 1024,
                'downloads' => 7,
                'traffic' => 7168,
            ],
        ];
    }

    public function testEmptyScrapeYieldsEmptyScrapeElement(): void
    {
        $xml = view_scrape_xml([]);
        $this->assertSame(
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><scrape></scrape>',
            $xml,
        );
    }

    public function testOutputIsWellFormedXml(): void
    {
        $xml = view_scrape_xml($this->fixture());

        // A bare list of <torrent> siblings would parse as multiple-root and
        // fail here; the <scrape> wrapper makes the document well-formed.
        $prev = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $doc = simplexml_load_string($xml);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $this->assertNotFalse($doc, 'XML failed to parse: '.implode('; ', array_map(
            static fn ($e) => trim($e->message),
            $errors,
        )));
        $this->assertSame('scrape', $doc->getName());
    }

    public function testSingleTorrentRendersAllFields(): void
    {
        $xml = view_scrape_xml($this->fixture());
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>', $xml);
        $this->assertStringContainsString('<info_hash>aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa</info_hash>', $xml);
        $this->assertStringContainsString('<seeders>2</seeders>', $xml);
        $this->assertStringContainsString('<leechers>1</leechers>', $xml);
        $this->assertStringContainsString('<peers>3</peers>', $xml);
        $this->assertStringContainsString('<size>1024</size>', $xml);
        $this->assertStringContainsString('<downloads>7</downloads>', $xml);
        $this->assertStringContainsString('<traffic>7168</traffic>', $xml);
    }

    public function testWrappingTorrentTagsArePresent(): void
    {
        $xml = view_scrape_xml($this->fixture());
        $this->assertSame(1, substr_count($xml, '<torrent>'));
        $this->assertSame(1, substr_count($xml, '</torrent>'));
    }

    public function testMultipleTorrentsEachGetTheirOwnElement(): void
    {
        $scrape = $this->fixture();
        $scrape['bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'] = [
            'info_hash' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            'seeders' => 0, 'leechers' => 5, 'peers' => 5,
            'size' => 0, 'downloads' => 0, 'traffic' => 0,
        ];
        $xml = view_scrape_xml($scrape);
        $this->assertSame(2, substr_count($xml, '<torrent>'));
        $this->assertStringContainsString('<info_hash>bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb</info_hash>', $xml);
    }

    public function testMinRequestIntervalElementOnlyWhenNonZero(): void
    {
        $with = view_scrape_xml($this->fixture(), 1800);
        $this->assertStringContainsString('<min_request_interval>1800</min_request_interval>', $with);
        // Still well-formed inside the <scrape> root.
        $this->assertNotFalse(simplexml_load_string($with));

        $this->assertStringNotContainsString('min_request_interval', view_scrape_xml($this->fixture(), 0));
    }

}
