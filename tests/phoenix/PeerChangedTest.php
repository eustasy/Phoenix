<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class PeerChangedTest extends PhoenixTestCase {

	/** @var array<string, mixed> */
	private array $current = array(
		'ipv4'   => '1.2.3.4',
		'ipv6'   => null,
		'portv4' => 80,
		'portv6' => 0,
		'state'  => 0,
	);

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once __DIR__.'/../../src/functions/peer.changed.php';
	}

	public function testReturnsTrueWhenOldIsFalse(): void {
		$this->assertTrue(peer_changed($this->current, false));
	}

	public function testReturnsFalseForIdenticalRow(): void {
		$this->assertFalse(peer_changed($this->current, $this->current));
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function changedFieldProvider(): iterable {
		yield 'ipv4'   => array('ipv4');
		yield 'ipv6'   => array('ipv6');
		yield 'portv4' => array('portv4');
		yield 'portv6' => array('portv6');
		yield 'state'  => array('state');
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('changedFieldProvider')]
	public function testDetectsFieldChange(string $field): void {
		$old = $this->current;
		$old[$field] = is_int($this->current[$field]) ? $this->current[$field] + 1 : '__changed__';
		$this->assertTrue(peer_changed($this->current, $old));
	}

}
