<?php

declare(strict_types=1);

////	install_build_config
// Renders sanitised install values as a phoenix.custom.php source string.
// var_export() handles the PHP-source-safe encoding of strings (escaping
// quotes, backslashes, and embedded null bytes) and booleans (emitting the
// bare `true` / `false` literals) for us. db_reset is always emitted as
// `false` so a freshly-installed tracker never re-runs setup unattended.
// admin_totp_secret is always emitted (empty when 2FA was skipped) so the key
// always exists, matching phoenix.default.php.
/** @param array<string, mixed> $values */
function install_build_config(array $values): string
{
    $keys = [
        'db_host',
        'db_user',
        'db_pass',
        'db_name',
        'db_prefix',
        'db_persist',
        'open_tracker',
        'public_index',
        'stats_enabled',
        'stats_geo',
        'admin_password',
    ];

    $config = '<?php'.PHP_EOL.PHP_EOL;
    foreach ($keys as $key) {
        $config .= '$settings['.var_export($key, true).'] = '.var_export($values[$key], true).';'.PHP_EOL;
    }
    $config .= '$settings[\'admin_totp_secret\'] = '.var_export($values['admin_totp_secret'] ?? '', true).';'.PHP_EOL;
    $config .= '$settings[\'db_reset\'] = false;'.PHP_EOL;

    return $config;
}
