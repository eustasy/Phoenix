<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class ApiUserIsAdminTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/api.user.is_admin.php';
    }

    public function testStarIsAdmin(): void
    {
        $this->assertTrue(\api_user_is_admin('*'));
    }

    public function testNamedUserIsNotAdmin(): void
    {
        $this->assertFalse(\api_user_is_admin('alice'));
    }

    public function testEmptyUserIsNotAdmin(): void
    {
        $this->assertFalse(\api_user_is_admin(''));
    }

    public function testLookalikeUsersAreNotAdmin(): void
    {
        // Only the exact '*' sentinel is the admin — no trimming or globbing.
        $this->assertFalse(\api_user_is_admin('**'));
        $this->assertFalse(\api_user_is_admin(' *'));
        $this->assertFalse(\api_user_is_admin('* '));
    }
}
