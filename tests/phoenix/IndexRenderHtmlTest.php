<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class IndexRenderHtmlTest extends PhoenixTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once self::$settings['functions'].'function.index.render.html.php';
	}

	/** @return list<array<string, mixed>> */
	private function fixture(): array {
		return array(array(
			'info_hash' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
			'name'      => 'Test Torrent',
			'size'      => 1024,
			'downloads' => 7,
			'seeders'   => 2,
			'leechers'  => 1,
			'peers'     => 3,
			'traffic'   => 7168,
		));
	}

	public function testEmptyIndexProducesEmptyList(): void {
		$html = index_render_html(array());
		$this->assertStringContainsString('<ul></ul>', $html);
	}

	public function testOutputStartsWithDoctype(): void {
		$html = index_render_html($this->fixture());
		$this->assertStringStartsWith('<!DocType html>', $html);
	}

	public function testTorrentNameAppearsInListItem(): void {
		$html = index_render_html($this->fixture());
		$this->assertStringContainsString('<li>Test Torrent', $html);
	}

	public function testStatisticsAreIncluded(): void {
		$html = index_render_html($this->fixture());
		$this->assertStringContainsString('2 seeders', $html);
		$this->assertStringContainsString('1 leechers', $html);
		$this->assertStringContainsString('7 downloads', $html);
	}

	public function testTorrentNameIsHtmlEscaped(): void {
		$index = array(array(
			'info_hash' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
			'name'      => '<script>alert(1)</script>',
			'size'      => 0, 'downloads' => 0, 'seeders' => 0,
			'leechers'  => 0, 'peers'     => 0, 'traffic' => 0,
		));
		$html = index_render_html($index);
		$this->assertStringNotContainsString('<script>', $html);
		$this->assertStringContainsString('&lt;script&gt;', $html);
	}

	public function testMultipleTorrentsEachGetAListItem(): void {
		$index = $this->fixture();
		$index[] = array(
			'info_hash' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
			'name'      => 'Second Torrent',
			'size'      => 0, 'downloads' => 3, 'seeders' => 1,
			'leechers'  => 0, 'peers'     => 1, 'traffic' => 0,
		);
		$html = index_render_html($index);
		$this->assertSame(2, substr_count($html, '<li>'));
		$this->assertStringContainsString('Second Torrent', $html);
	}

}
