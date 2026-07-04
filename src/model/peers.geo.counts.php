<?php

declare(strict_types=1);

////	peers_geo_counts
// Aggregate the active peers by country for the admin Geography page. Each
// peer's IP is resolved to an ISO country code via the GeoLite2 database — the
// same gate as stats_geo_lookup() (stats_geo on, geoip2 present, readable
// .mmdb), but the reader is opened ONCE for the whole batch rather than per
// peer. The IP is used only for the lookup and never stored; only the per-
// country counts are returned. Returns ['US' => 612, …] (countries that
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
        ! class_exists(\GeoIp2\Database\Reader::class) ||
        ! is_readable($settings['stats_geo_database'])
    ) {
        return [];
    }

    try {
        $reader = new \GeoIp2\Database\Reader($settings['stats_geo_database']);
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
            $record = $reader->country($ip);
            $country = strtoupper((string) ($record->country->isoCode ?? ''));
        } catch (\GeoIp2\Exception\AddressNotFoundException) {
            // Expected: this IP is not in the database.
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
