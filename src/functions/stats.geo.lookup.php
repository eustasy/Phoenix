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
// When any check fails, or the lookup throws for any reason, it returns empty
// codes. Geo enrichment must NEVER break an announce, so the reader open and
// lookup are wrapped in a catch-all — an unresolved address, a corrupt
// database, or a library error all degrade to ['country' => '', 'continent' => ''].

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
    } catch (\Throwable) {
        return $empty;
    }
}
