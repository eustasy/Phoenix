<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class AuthRehashPasswordTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/auth.rehash.password.php';
    }

    /** @var array<int, string> temp config files to remove in tearDown */
    private array $tmp = [];

    protected function tearDown(): void
    {
        foreach ($this->tmp as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->tmp = [];
    }

    private function tempConfig(string $hash): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'phx_rehash_');
        $this->tmp[] = $path;
        file_put_contents($path, "<?php\n\n\$settings['admin_password'] = ".var_export($hash, true).";\n");

        return $path;
    }

    private function storedHash(string $path): string
    {
        $read = static function (string $p): string {
            $settings = [];
            include $p;

            return is_string($settings['admin_password'] ?? null) ? $settings['admin_password'] : '';
        };

        return $read($path);
    }

    public function testRehashesAStaleHash(): void
    {
        // A bcrypt hash at a non-default cost is exactly what a PASSWORD_DEFAULT
        // change looks like: password_needs_rehash() is true, so the stored hash
        // must be rewritten to a current one that still verifies the password.
        $password = 'correct horse battery staple';
        $stale = password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);
        $this->assertTrue(password_needs_rehash($stale, PASSWORD_DEFAULT));

        $path = $this->tempConfig($stale);
        auth_rehash_password(array_merge(self::$settings, ['admin_password' => $stale]), $password, $path);

        $written = $this->storedHash($path);
        $this->assertNotSame($stale, $written, 'a stale hash must be rewritten');
        $this->assertTrue(password_verify($password, $written), 'the new hash must still verify the password');
        $this->assertFalse(password_needs_rehash($written, PASSWORD_DEFAULT), 'the new hash must be current');
    }

    public function testDoesNotRehashACurrentHash(): void
    {
        // A hash already at PASSWORD_DEFAULT needs no upgrade: the config must be
        // left byte-for-byte untouched (no spurious write on every login).
        $password = 'correct horse battery staple';
        $current = password_hash($password, PASSWORD_DEFAULT);
        $path = $this->tempConfig($current);

        auth_rehash_password(array_merge(self::$settings, ['admin_password' => $current]), $password, $path);

        // Unchanged: a rewrite would have produced a different (re-salted) hash.
        $this->assertSame($current, $this->storedHash($path));
    }

    public function testEmptyPasswordIsANoOp(): void
    {
        $stale = password_hash('x', PASSWORD_BCRYPT, ['cost' => 4]);
        $path = $this->tempConfig($stale);

        auth_rehash_password(array_merge(self::$settings, ['admin_password' => $stale]), '', $path);

        $this->assertSame($stale, $this->storedHash($path));
    }
}
