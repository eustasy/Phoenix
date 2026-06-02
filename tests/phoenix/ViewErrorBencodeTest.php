<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class ViewErrorBencodeTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/views/bencode.error.php';
    }

    public function testEmitsBencodeFailureReason(): void
    {
        $message = 'test error';
        $result = view_error_bencode($message);
        $this->assertSame(
            'd14:failure reason'.strlen($message).':'.$message.'e',
            $result,
        );
    }

    public function testHandlesEmptyError(): void
    {
        $result = view_error_bencode('');
        $this->assertSame('d14:failure reason0:e', $result);
    }

    public function testHandlesLongError(): void
    {
        $message = str_repeat('x', 200);
        $result = view_error_bencode($message);
        $this->assertSame(
            'd14:failure reason200:'.$message.'e',
            $result,
        );
    }

    public function testHandlesSpecialCharacters(): void
    {
        $message = 'Error: "quoted" & <special>';
        $result = view_error_bencode($message);
        $this->assertSame(
            'd14:failure reason'.strlen($message).':'.$message.'e',
            $result,
        );
    }

    public function testDoesNotExitOrEcho(): void
    {
        // Unlike tracker_error(), the view function should just return a string.
        // Capture any output and verify nothing was echoed.
        ob_start();
        $result = view_error_bencode('test');
        $output = ob_get_clean();

        $this->assertSame('', $output);
        $this->assertIsString($result);
        $this->assertStringStartsWith('d14:failure reason', $result);
    }

}
