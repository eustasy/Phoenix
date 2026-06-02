<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class InstallSanitizePostTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/install.sanitize.post.php';
    }

    public function testEmptyPostYieldsDefaults(): void
    {
        $values = install_sanitize_post([]);
        $this->assertSame('localhost', $values['db_host']);
        $this->assertSame('', $values['db_user']);
        $this->assertSame('', $values['db_pass']);
        $this->assertSame('phoenix', $values['db_name']);
        $this->assertSame('', $values['db_prefix']);
        $this->assertFalse($values['db_persist']);
        $this->assertFalse($values['open_tracker']);
        $this->assertFalse($values['public_index']);
        $this->assertSame('', $values['admin_password']);
    }

    public function testStripTagsFromHostAndUser(): void
    {
        $values = install_sanitize_post([
            'db_host' => '<script>alert(1)</script>localhost',
            'db_user' => 'phoenix<b>',
        ]);
        $this->assertSame('alert(1)localhost', $values['db_host']);
        $this->assertSame('phoenix', $values['db_user']);
    }

    public function testDbNameStrippedToSafeChars(): void
    {
        $values = install_sanitize_post(['db_name' => 'phoenix; DROP TABLE users; --']);
        $this->assertSame('phoenixDROPTABLEusers', $values['db_name']);
    }

    public function testDbPrefixStrippedToSafeChars(): void
    {
        $values = install_sanitize_post(['db_prefix' => 'phx`-_drop;']);
        $this->assertSame('phx_drop', $values['db_prefix']);
    }

    public function testEmptyDbNameFallsBackToPhoenix(): void
    {
        $values = install_sanitize_post(['db_name' => '']);
        $this->assertSame('phoenix', $values['db_name']);
    }

    public function testDbPassPreservedVerbatim(): void
    {
        $values = install_sanitize_post(['db_pass' => "p@ss'w\"ord<3"]);
        $this->assertSame("p@ss'w\"ord<3", $values['db_pass']);
    }

    public function testBooleanFieldsCoerceFromTruthyValues(): void
    {
        $values = install_sanitize_post([
            'db_persist' => '1',
            'open_tracker' => 'on',
            'public_index' => 'yes',
        ]);
        $this->assertTrue($values['db_persist']);
        $this->assertTrue($values['open_tracker']);
        $this->assertTrue($values['public_index']);
    }

    public function testBooleanFieldsAreFalseWhenAbsentOrEmpty(): void
    {
        $values = install_sanitize_post([
            'db_persist' => '',
            'open_tracker' => '0',
        ]);
        $this->assertFalse($values['db_persist']);
        $this->assertFalse($values['open_tracker']);
        $this->assertFalse($values['public_index']);
    }

    public function testAdminPasswordIsHashedNotStored(): void
    {
        $values = install_sanitize_post(['admin_password' => 'secret123']);
        $this->assertNotSame('secret123', $values['admin_password']);
        $this->assertTrue(password_verify('secret123', $values['admin_password']));
    }

    public function testEmptyAdminPasswordYieldsEmptyString(): void
    {
        $values = install_sanitize_post(['admin_password' => '']);
        $this->assertSame('', $values['admin_password']);
    }

}
