<?php

declare(strict_types=1);

////	db_is_configured
// Returns true when db_host, db_user, and db_name are all non-empty.
// db_pass may be empty (some MySQL servers permit passwordless local auth),
// so it is intentionally not checked here.
/** @param PhoenixSettings $settings */
function db_is_configured(array $settings): bool
{
    return ! empty($settings['db_host'])
        && ! empty($settings['db_user'])
        && ! empty($settings['db_name']);
}
