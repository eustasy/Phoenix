<?php

declare(strict_types=1);

////	settings_load
// Loads $settings from the default config file, then layers the optional
// custom config on top. If the custom file is missing, logs a warning and
// fills in hard-coded fallback values so the tracker can still attempt to
// boot (any subsequent db_is_configured() check will reject the dummy
// credentials). Returns the populated $settings array.

/** @return PhoenixSettings */
function settings_load(string $default_path, string $custom_path): array
{
    /** @var PhoenixSettings $settings */
    $settings = [];
    include $default_path;

    if (is_readable($custom_path)) {
        include $custom_path;

        return $settings;
    }

    error_log(
        'Configuration file "'.$custom_path.'" not readable.'.PHP_EOL.
        'Falling back to defaults.',
    );
    $settings['db_host'] = 'localhost';
    $settings['db_user'] = 'root';
    $settings['db_pass'] = 'Password1';
    $settings['db_name'] = 'phoenix';
    $settings['db_persist'] = true;
    $settings['open_tracker'] = true;

    return $settings;
}
