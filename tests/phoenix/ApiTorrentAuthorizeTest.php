<?php

declare(strict_types=1);

namespace Phoenix\Tests;

class ApiTorrentAuthorizeTest extends PhoenixTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/api.torrent.authorize.php';
    }

    ////	admin ('*') may act on anything

    public function testAdminMayActOnUnownedTorrent(): void
    {
        $this->assertTrue(\api_torrent_authorize('*', null));
    }

    public function testAdminMayActOnAnotherUsersTorrent(): void
    {
        $this->assertTrue(\api_torrent_authorize('*', 'alice'));
    }

    public function testAdminMayActOnAdminOwnedTorrent(): void
    {
        $this->assertTrue(\api_torrent_authorize('*', '*'));
    }

    ////	a normal user is scoped to its own torrents

    public function testOwnerMayActOnOwnTorrent(): void
    {
        $this->assertTrue(\api_torrent_authorize('alice', 'alice'));
    }

    public function testUserMayNotActOnAnotherUsersTorrent(): void
    {
        $this->assertFalse(\api_torrent_authorize('alice', 'bob'));
    }

    public function testUserMayNotActOnUnownedTorrent(): void
    {
        // Null-owner (announce-created) rows are admin-only.
        $this->assertFalse(\api_torrent_authorize('alice', null));
    }

    public function testUserMayNotActOnAdminOwnedTorrent(): void
    {
        $this->assertFalse(\api_torrent_authorize('alice', '*'));
    }
}
