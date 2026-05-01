<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class ScrapeRenderXmlTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once self::$settings['views'].'xml.scrape.php';
	}

	/** @return array<string, array<string, int|string>> */
	private function fixture(): array {
		return array(
			'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' => array(
				'info_hash' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
				'seeders'   => 2,
				'leechers'  => 1,
				'peers'     => 3,
				'size'      => 1024,
				'downloads' => 7,
				'traffic'   => 7168,
			),
		);
	}

	public function testEmptyScrapeYieldsHeaderOnly(): void {
		$xml = view_scrape_xml(array());
		$this->assertSame('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>', $xml);
	}

	public function testSingleTorrentRendersAllFields(): void {
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

	public function testWrappingTorrentTagsArePresent(): void {
		$xml = view_scrape_xml($this->fixture());
		$this->assertSame(1, substr_count($xml, '<torrent>'));
		$this->assertSame(1, substr_count($xml, '</torrent>'));
	}

	public function testMultipleTorrentsEachGetTheirOwnElement(): void {
		$scrape = $this->fixture();
		$scrape['bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'] = array(
			'info_hash' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
			'seeders'   => 0, 'leechers' => 5, 'peers' => 5,
			'size'      => 0, 'downloads' => 0, 'traffic' => 0,
		);
		$xml = view_scrape_xml($scrape);
		$this->assertSame(2, substr_count($xml, '<torrent>'));
		$this->assertStringContainsString('<info_hash>bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb</info_hash>', $xml);
	}

}
