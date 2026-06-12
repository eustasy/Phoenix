<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/functions/api.authenticate.request.php';

class ApiAuthenticateRequestTest extends PhoenixTestCase
{
    private const API_KEY = '__TEST_api_key__';

    /** @var array<string, mixed> */
    private array $getBackup;

    /** @var array<string, mixed> */
    private array $postBackup;

    protected function setUp(): void
    {
        parent::setUp();
        // Preserve $_GET/$_POST across the in-process tests.
        $this->getBackup = $_GET;
        $this->postBackup = $_POST;
    }

    protected function tearDown(): void
    {
        $_GET = $this->getBackup;
        $_POST = $this->postBackup;
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function settingsWithKeys(): array
    {
        $settings = self::$settings;
        $settings['api_keys'] = ['tester' => self::API_KEY];

        return $settings;
    }

    public function testReturnsUserForValidKeyFromPost(): void
    {
        $_GET = [];
        $_POST = ['key' => self::API_KEY];

        $this->assertSame('tester', \api_authenticate_request($this->settingsWithKeys()));
    }

    public function testReturnsUserForValidKeyFromGet(): void
    {
        $_POST = [];
        $_GET = ['key' => self::API_KEY];

        $this->assertSame('tester', \api_authenticate_request($this->settingsWithKeys()));
    }

    public function testRejectsInvalidKey(): void
    {
        // tracker_error() exits, so exercise the reject branch in a
        // subprocess and assert on its output + exit code.
        $result = $this->runErrorSubprocess(['key' => 'wrong-key']);

        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('API key is invalid.', $result['stdout']);
    }

    public function testRejectsMissingKey(): void
    {
        $result = $this->runErrorSubprocess([]);

        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('API key is invalid.', $result['stdout']);
    }

    public function testRejectsWhenApiDisabled(): void
    {
        // With no configured keys the API is off — refused before the key is
        // even read, so even a valid key gets the same exit.
        $result = $this->runErrorSubprocess(['key' => self::API_KEY], api_enabled: false);

        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('API is not enabled.', $result['stdout']);
    }

    /**
     * Run api_authenticate_request() in a subprocess with $_GET primed
     * (tracker_error exits, which would otherwise kill the PHPUnit worker).
     * $api_enabled toggles whether any keys are configured so the
     * API-disabled branch can be exercised.
     *
     * @param array<string, string> $params
     * @return array{stdout: string, stderr: string, exit: int}
     */
    private function runErrorSubprocess(array $params, bool $api_enabled = true): array
    {
        $params['json'] = '1';
        $api_keys = $api_enabled ? ['tester' => self::API_KEY] : [];

        return $this->runPhpSubprocess(
            '<?php
            $_GET = '.var_export($params, true).';
            require_once '.var_export(dirname(__DIR__).'/bootstrap.php', true).';
            require_once '.var_export(dirname(__DIR__, 2).'/src/functions/api.authenticate.request.php', true).';
            $settings = $GLOBALS[\'phoenix_settings\'];
            $settings[\'api_keys\'] = '.var_export($api_keys, true).';
            echo api_authenticate_request($settings);
            ',
        );
    }
}
