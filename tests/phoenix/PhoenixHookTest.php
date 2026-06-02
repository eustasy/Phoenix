<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class PhoenixHookTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/phoenix.hook.php';
    }

    public function testIsNoOpWhenHookFileIsNotReadable(): void
    {
        $peer = ['info_hash' => '__TEST__', 'peer_id' => '__TEST__'];
        ob_start();
        phoenix_hook('does.not.exist', self::$connection, self::$settings, self::$time, $peer);
        $output = ob_get_clean();
        $this->assertSame('', $output);
    }

    public function testIncludesHookFileWhenReadable(): void
    {
        $path = __DIR__.'/../../src/hooks/phoenix.test.synthetic.php';
        file_put_contents($path, "<?php\necho 'HOOK_RAN_'.\$peer['info_hash'];\n");

        try {
            $peer = ['info_hash' => '__SYNTH__', 'peer_id' => '__SYNTH__'];
            ob_start();
            phoenix_hook('test.synthetic', self::$connection, self::$settings, self::$time, $peer);
            $output = ob_get_clean();
            $this->assertSame('HOOK_RAN___SYNTH__', $output);
        } finally {
            unlink($path);
        }
    }

    public function testHookCanMutatePeerByReference(): void
    {
        $path = __DIR__.'/../../src/hooks/phoenix.test.mutate.php';
        file_put_contents($path, "<?php\n\$peer['mutated'] = true;\n");

        try {
            $peer = ['info_hash' => '__M__', 'peer_id' => '__M__'];
            phoenix_hook('test.mutate', self::$connection, self::$settings, self::$time, $peer);
            $this->assertArrayHasKey('mutated', $peer);
            $this->assertTrue($peer['mutated']);
        } finally {
            unlink($path);
        }
    }

}
