<?php

declare(strict_types=1);

////	db_is_configured
// Returns true when db_host, db_user, and db_name are all non-empty. The default
// config ships these empty, so an unconfigured install — no custom config, or a
// half-filled one — reads as "not configured" and the bootstrap errors cleanly
// instead of attempting a connection. db_pass may legitimately be empty (some
// MySQL servers permit passwordless local auth), so it is intentionally not checked.
/** @param PhoenixSettings $settings */
function db_is_configured(array $settings): bool
{
    return ! empty($settings['db_host'])
        && ! empty($settings['db_user'])
        && ! empty($settings['db_name']);
}
