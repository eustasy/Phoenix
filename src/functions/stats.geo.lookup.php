<?php

declare(strict_types=1);

////	stats_geo_lookup
// Resolves an IP to a minified geo location (ISO country + continent codes).
// The IP is used only for this lookup and is NEVER stored — only the coarse
// codes are. Geo is active only when ALL of these hold:
//   * $settings['stats_geo'] === true
//   * the maxmind-db reader is installed (class_exists)
//   * $settings['stats_geo_database'] points at a readable .mmdb
//   * a non-empty IP was supplied
// When any check fails it returns empty codes. Geo enrichment must NEVER break
// an announce, so the reader open and lookup are wrapped: an unresolved address
// (a null record) is expected and silent, while a corrupt database or a library
// error still degrades to ['country' => '', 'continent' => ''] but is
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
        ! class_exists(\MaxMind\Db\Reader::class) ||
        ! is_readable($settings['stats_geo_database'])
    ) {
        return $empty;
    }

    // The reader is held for the life of the process, keyed by path. Opening
    // one costs ~159us against ~9us for the lookup it wraps, so constructing it
    // per call made this 18x its necessary cost — on the announce path, which
    // runs it once per announce. A persistent worker reuses it across requests;
    // php-fpm rebuilds it per request, which is still once instead of once per
    // call. Keyed by path so a settings change mid-process is honoured.
    static $readers = [];
    $path = $settings['stats_geo_database'];

    try {
        // The raw record rather than GeoIp2's model graph: with ext-maxminddb
        // the Country/Continent objects cost more than the lookup they wrap.
        if (! isset($readers[$path])) {
            $readers[$path] = new \MaxMind\Db\Reader($path);
        }
        $record = $readers[$path]->get($ip);
        if (! is_array($record)) {
            // Expected: the IP simply is not in the database. Benign.
            return $empty;
        }

        return [
            'country' => strtoupper((string) ($record['country']['iso_code'] ?? '')),
            'continent' => strtoupper((string) ($record['continent']['code'] ?? '')),
        ];
    } catch (\InvalidArgumentException) {
        // Not an address the reader can parse. Benign; never reported.
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
