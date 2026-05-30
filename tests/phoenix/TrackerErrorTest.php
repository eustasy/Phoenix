<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class TrackerErrorTest extends PhoenixTestCase {

	private const FUNCTION_PATH = __DIR__.'/../../src/functions/tracker.error.php';

	/**
	 * Drive tracker_error() in a subprocess (it calls exit(2) which would
	 * otherwise kill the PHPUnit worker), with the given $_GET seeded.
	 *
	 * @param array<string, string> $get
	 * @return array{stdout: string, stderr: string, exit: int}
	 */
	private function runTrackerError(string $message, array $get = []): array {
		$script = '<?php '.
			'parse_str('.var_export(http_build_query($get), true).', $_GET); '.
			'require '.var_export(self::FUNCTION_PATH, true).'; '.
			'tracker_error('.var_export($message, true).');';
		return $this->runPhpSubprocess($script);
	}

	////	Bencode (default)

	public function testBencodeIsTheDefaultFormat(): void {
		$result = $this->runTrackerError('test error');
		$this->assertSame(2, $result['exit']);
		$this->assertSame('d14:failure reason10:test errore', $result['stdout']);
	}

	public function testBencodeHandlesEmptyMessage(): void {
		$result = $this->runTrackerError('');
		$this->assertSame(2, $result['exit']);
		$this->assertSame('d14:failure reason0:e', $result['stdout']);
	}

	public function testBencodeIsByteCounted(): void {
		// Multibyte content: strlen counts bytes, which is what bencode wants.
		$message = 'héllo'; // 6 bytes (h, é=2, l, l, o)
		$result  = $this->runTrackerError($message);
		$this->assertSame(2, $result['exit']);
		$this->assertSame('d14:failure reason'.strlen($message).':'.$message.'e', $result['stdout']);
	}

	////	XML

	public function testXmlFormatWhenXmlFlagSet(): void {
		$result = $this->runTrackerError('test error', ['xml' => '1']);
		$this->assertSame(2, $result['exit']);
		$this->assertSame(
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><error>test error</error>',
			$result['stdout']
		);
	}

	public function testXmlEscapesSpecialCharacters(): void {
		$result = $this->runTrackerError('a < b & c > d', ['xml' => '1']);
		$this->assertSame(2, $result['exit']);
		// Body must be parseable; the entities round-trip back to the original.
		$doc = simplexml_load_string($result['stdout']);
		$this->assertNotFalse($doc);
		$this->assertSame('a < b & c > d', (string) $doc);
	}

	public function testXmlHandlesEmptyMessage(): void {
		$result = $this->runTrackerError('', ['xml' => '1']);
		$this->assertSame(2, $result['exit']);
		$this->assertSame(
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><error></error>',
			$result['stdout']
		);
	}

	////	JSON

	public function testJsonFormatWhenJsonFlagSet(): void {
		$result = $this->runTrackerError('test error', ['json' => '1']);
		$this->assertSame(2, $result['exit']);
		$this->assertSame('{"error":"test error"}', $result['stdout']);
	}

	public function testJsonEscapesSpecialCharacters(): void {
		$result  = $this->runTrackerError("with \"quotes\" and \\ backslash", ['json' => '1']);
		$this->assertSame(2, $result['exit']);
		$decoded = json_decode($result['stdout'], true);
		$this->assertSame(['error' => 'with "quotes" and \\ backslash'], $decoded);
	}

	public function testJsonHandlesEmptyMessage(): void {
		$result = $this->runTrackerError('', ['json' => '1']);
		$this->assertSame(2, $result['exit']);
		$this->assertSame('{"error":""}', $result['stdout']);
	}

	////	Precedence

	public function testXmlWinsWhenBothXmlAndJsonAreSet(): void {
		// tracker_error checks $_GET['xml'] first; ?xml=1&json=1 must produce
		// XML, not JSON. Pin this so a future reorder doesn't silently flip
		// the contract.
		$result = $this->runTrackerError('test', ['xml' => '1', 'json' => '1']);
		$this->assertSame(2, $result['exit']);
		$this->assertStringStartsWith('<?xml', $result['stdout']);
		$this->assertStringNotContainsString('"error"', $result['stdout']);
	}

	public function testEmptyXmlValueStillSelectsXml(): void {
		// `?xml=` (no value) is enough — isset() drives the branch, not the
		// value. Same for `?json=`.
		$result = $this->runTrackerError('test', ['xml' => '']);
		$this->assertSame(2, $result['exit']);
		$this->assertStringStartsWith('<?xml', $result['stdout']);
	}

	public function testEmptyJsonValueStillSelectsJson(): void {
		$result = $this->runTrackerError('test', ['json' => '']);
		$this->assertSame(2, $result['exit']);
		$this->assertStringStartsWith('{', $result['stdout']);
	}

}
