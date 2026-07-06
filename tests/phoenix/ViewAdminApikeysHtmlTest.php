<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../src/views/html.admin.apikeys.php';

class ViewAdminApikeysHtmlTest extends TestCase
{
    /**
     * @param array<string, string> $api_keys
     * @return array<string, mixed>
     */
    private function settings(array $api_keys = []): array
    {
        return [
            'phoenix_version' => 'Phoenix Test v.0',
            'admin_password' => 'hash',
            'api_keys' => $api_keys,
            'nav_counts' => [],
        ];
    }

    public function testRendersCreateFormWhenWritable(): void
    {
        $html = \view_admin_apikeys_html($this->settings(), true, false, 'tok', null);
        $this->assertStringContainsString('name="process" value="apikey_create"', $html);
        $this->assertStringContainsString('Generate key', $html);
    }

    public function testShowsNewKeyOnce(): void
    {
        $key = 'phx_'.str_repeat('a', 64);
        $html = \view_admin_apikeys_html($this->settings(), true, 'API key created.', 'tok', $key);
        $this->assertStringContainsString($key, $html);
        $this->assertStringContainsString('will not be shown again', $html);
    }

    public function testListsExistingKeysWithRevokeAndAdminBadge(): void
    {
        $html = \view_admin_apikeys_html($this->settings(['alice' => str_repeat('a', 64), '*' => str_repeat('b', 64)]), true, false, 'tok');
        $this->assertStringContainsString('alice', $html);
        $this->assertStringContainsString('value="apikey_revoke"', $html);
        $this->assertStringContainsString('badge', $html); // '*' gets an admin badge
    }

    public function testReadOnlyNoticeWhenNotWritable(): void
    {
        $html = \view_admin_apikeys_html($this->settings(['alice' => str_repeat('a', 64)]), false, false, 'tok');
        $this->assertStringContainsString('not writable', $html);
        // No create control and no revoke buttons when config/ can't be written.
        $this->assertStringNotContainsString('Generate key', $html);
        $this->assertStringNotContainsString('value="apikey_revoke"', $html);
    }

    public function testEmptyStateWhenNoKeys(): void
    {
        $html = \view_admin_apikeys_html($this->settings(), true, false, 'tok');
        $this->assertStringContainsString('No API keys yet', $html);
    }
}
