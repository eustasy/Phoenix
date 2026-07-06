<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../src/functions/api.key.generate.php';

class ApiKeyGenerateTest extends TestCase
{
    public function testFormatIsPrefixedHex(): void
    {
        // 'phx_' + 32 random bytes as hex (64 chars).
        $this->assertMatchesRegularExpression('/^phx_[0-9a-f]{64}$/', api_key_generate());
    }

    public function testKeysAreUnique(): void
    {
        $keys = [];
        for ($i = 0; $i < 200; $i++) {
            $keys[api_key_generate()] = true;
        }
        $this->assertCount(200, $keys);
    }
}
