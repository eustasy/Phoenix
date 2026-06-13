<?php

declare(strict_types=1);

////	api_user_is_admin
// Whether an authenticated API user is the admin. The reserved owner '*' is
// the admin: a key issued to user '*' (in $settings['api_keys']), or an
// admin.php session resolved by api_authenticate_request()'s callers, acts on
// any torrent regardless of owner. Every other user is scoped to its own rows.

function api_user_is_admin(string $user): bool
{
    return $user === '*';
}
