<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class PhoenixHookEventTest extends PhoenixTestCase
{
    private const HOOKS_DIR = __DIR__.'/../../src/hooks';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/phoenix.hook.event.php';
    }

    protected function tearDown(): void
    {
        foreach (['phoenix_test_hook_ctx', 'phoenix_test_hook_reentry'] as $key) {
            unset($GLOBALS[$key]);
        }
    }

    /** Drop a throwaway hook file in src/hooks/ and return its path. */
    private function writeHook(string $name, string $body): string
    {
        $path = self::HOOKS_DIR.'/phoenix.'.$name.'.php';
        file_put_contents($path, "<?php\n\n".$body."\n");

        return $path;
    }

    public function testNoOpWhenHandlerMissing(): void
    {
        // A name with no hook file is a silent no-op, never an error.
        \phoenix_hook_event('no_such_event_'.bin2hex(random_bytes(4)), ['message' => 'x']);
        $this->assertTrue(true);
    }

    public function testFiresHandlerWithContext(): void
    {
        $name = 'phxtest_ctx_'.bin2hex(random_bytes(4));
        $path = $this->writeHook($name, '$GLOBALS["phoenix_test_hook_ctx"] = $context;');
        try {
            \phoenix_hook_event($name, ['message' => 'hello', 'source' => 'unit']);
        } finally {
            unlink($path);
        }

        $context = $GLOBALS['phoenix_test_hook_ctx'] ?? null;
        $this->assertIsArray($context);
        $this->assertSame('hello', $context['message']);
        $this->assertSame('unit', $context['source']);
    }

    public function testReentrancyGuardStopsRecursion(): void
    {
        // The handler re-fires its own event; the guard must drop the nested call
        // so the body runs exactly once rather than recursing forever.
        $name = 'phxtest_reentry_'.bin2hex(random_bytes(4));
        $path = $this->writeHook(
            $name,
            '$GLOBALS["phoenix_test_hook_reentry"] = ($GLOBALS["phoenix_test_hook_reentry"] ?? 0) + 1; '.
            '\phoenix_hook_event('.var_export($name, true).', []);',
        );
        try {
            \phoenix_hook_event($name, []);
        } finally {
            unlink($path);
        }

        $this->assertSame(1, $GLOBALS['phoenix_test_hook_reentry'] ?? null);
    }

    public function testSwallowsThrowingHandler(): void
    {
        // A throwing handler must not propagate — an error handler cannot be
        // allowed to crash the request it is reporting on.
        $name = 'phxtest_throw_'.bin2hex(random_bytes(4));
        $path = $this->writeHook($name, 'throw new \RuntimeException("boom");');
        try {
            \phoenix_hook_event($name, ['message' => 'x']);
            $this->assertTrue(true);
        } finally {
            unlink($path);
        }
    }

    public function testTrackerErrorWithReportFiresErrorHook(): void
    {
        // Swap the shipped no-op error hook for a marker-writer, then confirm
        // tracker_error($msg, ..., report: true) reaches it (in a subprocess,
        // since tracker_error exit()s).
        $hookPath = self::HOOKS_DIR.'/phoenix.error.php';
        $backup = $hookPath.'.audit-bak';
        $marker = tempnam(sys_get_temp_dir(), 'phx_marker_');
        $this->assertNotFalse($marker);

        $this->assertTrue(rename($hookPath, $backup));
        file_put_contents(
            $hookPath,
            "<?php\n\nfile_put_contents(".var_export($marker, true).
            ", (\$context['message'] ?? '').'|'.(\$context['source'] ?? ''));\n",
        );
        try {
            $script = '<?php require '.var_export(__DIR__.'/../bootstrap.php', true).'; '.
                'tracker_error("boom-report", null, true);';
            $result = $this->runPhpSubprocess($script);

            $this->assertSame(2, $result['exit']);
            $this->assertSame('boom-report|tracker_error', (string) file_get_contents($marker));
        } finally {
            unlink($hookPath);
            rename($backup, $hookPath);
            @unlink($marker);
        }
    }

    public function testBootstrapFiresInitWhenReportEnabled(): void
    {
        // Swap the shipped no-op init hook for a marker-writer and enable
        // report_errors, then confirm a bare bootstrap fires 'init' with the
        // settings in context (in a subprocess, so the real phoenix.php runs).
        $initPath = self::HOOKS_DIR.'/phoenix.init.php';
        $initBackup = $initPath.'.audit-bak';
        $configPath = __DIR__.'/../../config/phoenix.custom.php';
        $configBackup = $configPath.'.audit-bak';
        $marker = tempnam(sys_get_temp_dir(), 'phx_init_');
        $this->assertNotFalse($marker);

        $this->assertTrue(rename($initPath, $initBackup));
        file_put_contents(
            $initPath,
            "<?php\n\n\$version = is_array(\$context['settings'] ?? null) ? (\$context['settings']['phoenix_version'] ?? '') : '';\n".
            'file_put_contents('.var_export($marker, true).", 'init|'.\$version);\n",
        );
        $this->assertTrue(copy($configPath, $configBackup));
        file_put_contents($configPath, "\n\$settings['report_errors'] = true;\n", FILE_APPEND);

        try {
            $script = '<?php require '.var_export(__DIR__.'/../bootstrap.php', true).';';
            $this->runPhpSubprocess($script);

            $this->assertStringContainsString('init|v', (string) file_get_contents($marker));
        } finally {
            unlink($initPath);
            rename($initBackup, $initPath);
            rename($configBackup, $configPath);
            @unlink($marker);
        }
    }
}
