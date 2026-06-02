<?php

declare(strict_types=1);

////	install_sanitize_post
// Sanitises the install form submission. db_name and db_prefix are restricted
// to [a-zA-Z0-9_] because they're interpolated into SQL identifiers (backtick
// quoted but otherwise unescaped); admin_password is bcrypt-hashed when
// supplied. Returns an associative array of the values destined for
// phoenix.custom.php.
function install_sanitize_post(array $post): array
{
    return [
        'db_host' => ! empty($post['db_host']) ? strip_tags($post['db_host']) : 'localhost',
        'db_user' => ! empty($post['db_user']) ? strip_tags($post['db_user']) : '',
        'db_pass' => $post['db_pass'] ?? '',
        'db_name' => ! empty($post['db_name']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $post['db_name']) : 'phoenix',
        'db_prefix' => ! empty($post['db_prefix']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $post['db_prefix']) : '',
        'db_persist' => ! empty($post['db_persist']),
        'open_tracker' => ! empty($post['open_tracker']),
        'public_index' => ! empty($post['public_index']),
        'admin_password' => isset($post['admin_password']) && $post['admin_password'] !== ''
            ? password_hash($post['admin_password'], PASSWORD_DEFAULT)
            : '',
    ];
}
