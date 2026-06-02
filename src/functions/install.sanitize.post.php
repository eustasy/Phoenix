<?php

declare(strict_types=1);

////	install_sanitize_post
// Sanitises the install form submission. db_name and db_prefix are restricted
// to [a-zA-Z0-9_] because they're interpolated into SQL identifiers (backtick
// quoted but otherwise unescaped); admin_password is bcrypt-hashed when
// supplied. Returns an associative array of the values destined for
// phoenix.custom.php.
/**
 * @param array<string, mixed> $post
 * @return array{db_host: string, db_user: string, db_pass: string, db_name: string, db_prefix: string, db_persist: bool, open_tracker: bool, public_index: bool, admin_password: string}
 */
function install_sanitize_post(array $post): array
{
    $db_host = $post['db_host'] ?? null;
    $db_user = $post['db_user'] ?? null;
    $db_pass = $post['db_pass'] ?? null;
    $db_name = $post['db_name'] ?? null;
    $db_prefix = $post['db_prefix'] ?? null;
    $admin_password = $post['admin_password'] ?? null;

    return [
        'db_host' => is_string($db_host) && $db_host !== '' ? strip_tags($db_host) : 'localhost',
        'db_user' => is_string($db_user) && $db_user !== '' ? strip_tags($db_user) : '',
        'db_pass' => is_string($db_pass) ? $db_pass : '',
        'db_name' => is_string($db_name) && $db_name !== '' ? (string) preg_replace('/[^a-zA-Z0-9_]/', '', $db_name) : 'phoenix',
        'db_prefix' => is_string($db_prefix) && $db_prefix !== '' ? (string) preg_replace('/[^a-zA-Z0-9_]/', '', $db_prefix) : '',
        'db_persist' => ! empty($post['db_persist']),
        'open_tracker' => ! empty($post['open_tracker']),
        'public_index' => ! empty($post['public_index']),
        'admin_password' => is_string($admin_password) && $admin_password !== ''
            ? password_hash($admin_password, PASSWORD_DEFAULT)
            : '',
    ];
}
