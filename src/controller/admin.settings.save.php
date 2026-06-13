<?php

declare(strict_types=1);

////	admin_settings_save_action
// Handles the Settings page flag toggles (process=settings). Reads the four
// boolean flags from the submitted checkboxes (an unchecked box sends nothing,
// so isset() is the on/off signal) and persists them via config_write, which
// preserves every other custom setting. Returns a message for the panel. The
// caller has already verified the CSRF token and that the config is writable.

function admin_settings_save_action(string $config_path): string
{
    require_once __DIR__.'/../functions/config.write.php';

    $values = [
        'open_tracker' => isset($_POST['open_tracker']),
        'public_index' => isset($_POST['public_index']),
        'full_scrape' => isset($_POST['full_scrape']),
        'db_reset' => isset($_POST['db_reset']),
    ];

    if (! config_write($config_path, $values)) {
        return 'Could not write the configuration file.';
    }

    return 'Settings saved.';
}
