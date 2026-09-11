<?php

declare(strict_types=1);

////	peers_geo_counts
// Aggregate the active peers by country for the admin Geography page. Each
// peer's IP is resolved to an ISO country code via the GeoLite2 database — the
// same gate as stats_geo_lookup() (stats_geo on, the reader present, readable
// .mmdb), but the reader is opened ONCE for the whole batch rather than per
// peer. Nothing is written back: the country is derived per request and only
// the per-country counts are returned. The addresses themselves are read from
// the peers table, which is where a tracker keeps them. Returns ['US' => 612, …] (countries that
// resolved), or an empty array when geo isn't configured or no peer resolves.

/**
 * @param PhoenixSettings $settings
 * @return array<string, int>
 */
function peers_geo_counts(mysqli $connection, array $settings): array
{
    // Geo must be enabled, the library present, and the database readable.
    if (
        $settings['stats_geo'] !== true ||
        ! class_exists(\MaxMind\Db\Reader::class) ||
        ! is_readable($settings['stats_geo_database'])
    ) {
        return [];
    }

    try {
        $reader = new \MaxMind\Db\Reader($settings['stats_geo_database']);
    } catch (\Throwable $e) {
        // A Reader that will not construct means a corrupt/unreadable .mmdb —
        // always unexpected, so surface it rather than silently disabling geo.
        if ($settings['report_errors']) {
            require_once __DIR__.'/../functions/phoenix.hook.event.php';
            phoenix_hook_event('error', ['throwable' => $e, 'source' => 'peers_geo_counts']);
        }

        return [];
    }

    // Group identical addresses so each distinct IP is looked up once.
    $result = mysqli_query(
        $connection,
        'SELECT `ipv4`, `ipv6`, COUNT(*) AS `n` '.
        'FROM `'.$settings['db_prefix'].'peers` '.
        'GROUP BY `ipv4`, `ipv6`;',
    );
    if (! $result instanceof mysqli_result) {
        tracker_error('Unable to get peers.');
    }

    $geo_reported = false;
    $counts = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Prefer the IPv4 address; fall back to IPv6.
        $ip = is_string($row['ipv4']) && $row['ipv4'] !== ''
            ? $row['ipv4']
            : (is_string($row['ipv6']) ? $row['ipv6'] : '');
        if ($ip === '') {
            continue;
        }

        try {
            // The raw record, not GeoIp2's model graph: this page resolves one
            // address per distinct peer, and with ext-maxminddb the model
            // objects cost more than the lookup. A miss returns null instead of
            // throwing, and a record carrying only `registered_country` has no
            // `country` key — both are simply unknown, as they were before.
            $record = $reader->get($ip);
            $country = is_array($record)
                ? strtoupper((string) ($record['country']['iso_code'] ?? ''))
                : '';
        } catch (\InvalidArgumentException) {
            // Not an address this reader can parse.
            $country = '';
        } catch (\Throwable $e) {
            // Unexpected mid-batch error; report once per call to avoid a flood.
            if (! $geo_reported && $settings['report_errors']) {
                require_once __DIR__.'/../functions/phoenix.hook.event.php';
                phoenix_hook_event('error', ['throwable' => $e, 'source' => 'peers_geo_counts']);
                $geo_reported = true;
            }
            $country = '';
        }
        if ($country === '') {
            continue;
        }

        $counts[$country] = ($counts[$country] ?? 0) + intval($row['n']);
    }

    return $counts;
}
