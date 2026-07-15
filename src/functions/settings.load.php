<?php

declare(strict_types=1);

////	settings_load
// Loads $settings from the default config file, then layers the optional custom
// config on top when it exists. If the custom file is missing or unreadable,
// logs a warning and returns the defaults untouched: the default file ships the
// DB credentials empty, which db_is_configured() treats as "not configured", so
// the bootstrap reports that cleanly instead of inventing credentials to connect
// with. Returns the $settings array.

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

    return $settings;
}
