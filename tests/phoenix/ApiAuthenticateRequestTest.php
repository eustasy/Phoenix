<?php

declare(strict_types=1);

namespace Phoenix\Tests;

require_once __DIR__.'/../../src/functions/api.authenticate.request.php';

class ApiAuthenticateRequestTest extends PhoenixTestCase
{
    private const API_KEY = '__TEST_api_key__';

    /** @var array<string, mixed> */
    private array $serverBackup;

    protected function setUp(): void
    {
        parent::setUp();
        // Preserve $_SERVER (the Authorization header lives here) across tests.
        $this->serverBackup = $_SERVER;
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function settingsWithKeys(): array
    {
        $settings = self::$settings;
        $settings['api_keys'] = ['tester' => hash('sha256', self::API_KEY)];

        return $settings;
    }

    public function testReturnsUserForValidBearerKey(): void
    {
        // The read auth resolves a key from the Authorization header; no CSRF
        // and no session needed on the key path.
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.self::API_KEY;

        $this->assertSame('tester', \api_authenticate_request($this->settingsWithKeys()));
    }

    public function testReturnsAdminForAdminKey(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer __TEST_admin_key__';
        $settings = self::$settings;
        $settings['api_keys'] = ['tester' => hash('sha256', self::API_KEY), '*' => hash('sha256', '__TEST_admin_key__')];

        $this->assertSame('*', \api_authenticate_request($settings));
    }

    public function testRejectsInvalidKey(): void
    {
        // tracker_error() exits, so exercise the reject branch in a subprocess.
        $result = $this->runErrorSubprocess('Bearer wrong-key');

        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('API key is invalid.', $result['stdout']);
    }

    public function testRejectsMissingCredential(): void
    {
        // No Authorization header and no admin session → refused.
        $result = $this->runErrorSubprocess(null);

        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Authorization required.', $result['stdout']);
    }

    public function testRejectsWhenApiDisabled(): void
    {
        // A key is presented but no keys are configured: the key path reports
        // the API is off.
        $result = $this->runErrorSubprocess('Bearer '.self::API_KEY, api_enabled: false);

        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('API is not enabled.', $result['stdout']);
    }

    /**
     * Run api_authenticate_request() in a subprocess (tracker_error exits,
     * which would otherwise kill the PHPUnit worker). $authHeader is the raw
     * Authorization header value, or null to send none.
     *
     * @return array{stdout: string, stderr: string, exit: int}
     */
    private function runErrorSubprocess(?string $authHeader, bool $api_enabled = true): array
    {
        $api_keys = $api_enabled ? ['tester' => hash('sha256', self::API_KEY)] : [];
        $server_setup = $authHeader === null
            ? ''
            : '$_SERVER[\'HTTP_AUTHORIZATION\'] = '.var_export($authHeader, true).';';

        return $this->runPhpSubprocess(
            '<?php
            $_GET = [\'json\' => \'1\'];
            '.$server_setup.'
            require_once '.var_export(dirname(__DIR__).'/bootstrap.php', true).';
            require_once '.var_export(dirname(__DIR__, 2).'/src/functions/api.authenticate.request.php', true).';
            $settings = $GLOBALS[\'phoenix_settings\'];
            $settings[\'api_keys\'] = '.var_export($api_keys, true).';
            echo api_authenticate_request($settings);
            ',
        );
    }
}
