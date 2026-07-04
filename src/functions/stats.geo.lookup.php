<?php

declare(strict_types=1);

////	stats_geo_lookup
// Resolves an IP to a minified geo location (ISO country + continent codes).
// The IP is used only for this lookup and is NEVER stored — only the coarse
// codes are. Geo is active only when ALL of these hold:
//   * $settings['stats_geo'] === true
//   * the geoip2 library is installed (class_exists)
//   * $settings['stats_geo_database'] points at a readable .mmdb
//   * a non-empty IP was supplied
// When any check fails it returns empty codes. Geo enrichment must NEVER break
// an announce, so the reader open and lookup are wrapped: an unresolved address
// (AddressNotFoundException) is expected and silent, while a corrupt database or
// library error still degrades to ['country' => '', 'continent' => ''] but is
// reported via phoenix_hook_event('error') when report_errors is on — so a
// broken .mmdb is not invisible.

/**
 * @param PhoenixSettings $settings
 * @return array{country: string, continent: string}
 */
function stats_geo_lookup(array $settings, string $ip): array
{
    $empty = ['country' => '', 'continent' => ''];

    if (
        $settings['stats_geo'] !== true ||
        $ip === '' ||
        ! class_exists(\GeoIp2\Database\Reader::class) ||
        ! is_readable($settings['stats_geo_database'])
    ) {
        return $empty;
    }

    try {
        $reader = new \GeoIp2\Database\Reader($settings['stats_geo_database']);
        $record = $reader->country($ip);

        return [
            'country' => strtoupper((string) ($record->country->isoCode ?? '')),
            'continent' => strtoupper((string) ($record->continent->code ?? '')),
        ];
    } catch (\GeoIp2\Exception\AddressNotFoundException) {
        // Expected: the IP simply is not in the database. Benign; never reported.
        return $empty;
    } catch (\Throwable $e) {
        // Unexpected (a corrupt/unreadable .mmdb, a library error): surface it so
        // a broken geo database is not silently masked as "no geo data".
        if ($settings['report_errors']) {
            require_once __DIR__.'/phoenix.hook.event.php';
            phoenix_hook_event('error', ['throwable' => $e, 'source' => 'stats_geo_lookup']);
        }

        return $empty;
    }
}
