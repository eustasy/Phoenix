<?php

declare(strict_types=1);

////	api_torrent_authorize
// Decide whether the authenticated API user $user may act on a torrent owned by
// $owner (its `user` column, possibly null). The admin ('*') may act on any
// torrent, including announce-created rows with no owner. A normal user may act
// only on torrents it owns; null-owner rows are therefore admin-only. Callers
// map a false result to tracker_error('Torrent not found.') — the same error a
// missing row gets, so ownership never discloses existence.

function api_torrent_authorize(string $user, ?string $owner): bool
{
    require_once __DIR__.'/api.user.is_admin.php';
    if (api_user_is_admin($user)) {
        return true;
    }

    return $owner !== null && $owner === $user;
}
