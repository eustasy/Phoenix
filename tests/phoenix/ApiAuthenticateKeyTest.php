<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class ApiAuthenticateKeyTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/api.authenticate.key.php';
    }

    /**
     * Stored api_keys are SHA-256 hashes of the issued key, so hash the given
     * plaintext keys here; the tests still present the plaintext.
     *
     * @param array<string, string> $api_keys
     * @return array<string, mixed>
     */
    private function settingsWithKeys(array $api_keys): array
    {
        $hashed = [];
        foreach ($api_keys as $user => $key) {
            $hashed[$user] = hash('sha256', $key);
        }
        $settings = self::$settings;
        $settings['api_keys'] = $hashed;

        return $settings;
    }

    public function testReturnsUserForMatchingKey(): void
    {
        $settings = $this->settingsWithKeys([
            'alice' => 'key-alice',
            'bob' => 'key-bob',
        ]);

        $this->assertSame('alice', \api_authenticate_key($settings, 'key-alice'));
        $this->assertSame('bob', \api_authenticate_key($settings, 'key-bob'));
    }

    public function testReturnsFalseForUnknownKey(): void
    {
        $settings = $this->settingsWithKeys(['alice' => 'key-alice']);

        $this->assertFalse(\api_authenticate_key($settings, 'wrong-key'));
    }

    public function testReturnsFalseForEmptyKey(): void
    {
        $settings = $this->settingsWithKeys(['alice' => 'key-alice']);

        $this->assertFalse(\api_authenticate_key($settings, ''));
    }

    public function testReturnsFalseWhenNoKeysConfigured(): void
    {
        $settings = $this->settingsWithKeys([]);

        $this->assertFalse(\api_authenticate_key($settings, 'key-alice'));
    }

    public function testDoesNotMatchUserNameAsKey(): void
    {
        // The user is the array key, not a credential — supplying it must fail.
        $settings = $this->settingsWithKeys(['alice' => 'key-alice']);

        $this->assertFalse(\api_authenticate_key($settings, 'alice'));
    }

    public function testNumericUserIsReturnedAsString(): void
    {
        // PHP coerces numeric-string array keys to int; the contract is a
        // string user either way.
        $settings = $this->settingsWithKeys(['42' => 'key-42']);

        $this->assertSame('42', \api_authenticate_key($settings, 'key-42'));
    }
}
