<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class ViewErrorJsonTest extends TestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once __DIR__.'/../../src/views/json.error.php';
	}

	public function testRendersErrorAsJsonObject(): void {
		$this->assertSame('{"error":"oops"}', view_error_json('oops'));
	}

	public function testHandlesEmptyMessage(): void {
		$this->assertSame('{"error":""}', view_error_json(''));
	}

	public function testEscapesQuotesAndBackslashes(): void {
		$out     = view_error_json('with "quotes" and \\ backslash');
		$decoded = json_decode($out, true);
		$this->assertSame(['error' => 'with "quotes" and \\ backslash'], $decoded);
	}

	public function testProducesValidJson(): void {
		// Round-trip: any string we throw at the encoder must come back intact.
		foreach ( ['simple', '', 'a < b & c > d', "tab\tnewline\n", 'unicode ☃ snowman'] as $msg ) {
			$decoded = json_decode(view_error_json($msg), true);
			$this->assertSame(['error' => $msg], $decoded);
		}
	}

}
