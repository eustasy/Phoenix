<?php

declare(strict_types=1);

////	stats_geo_lookup_batch
// Resolve many IPs to their ISO country code and English country name in one
// pass, for the admin Peers table's Country column. Same gate as
// stats_geo_lookup() (stats_geo on, the reader present, readable .mmdb), but the
// reader is opened ONCE for the whole batch and each distinct address is looked
// up once — a page of peers is up to admin_peers_rows rows, and per-row reader
// construction would dominate the render.
//
// The name comes from the database record rather than a table of our own, so it
// cannot drift from the codes the same database returns.
//
// The IPs are used only for the lookup and are NEVER stored, matching
// stats_geo_lookup()'s contract. Returns a map keyed by the IP passed in;
// addresses that do not resolve are simply absent, so a caller reads
// $map[$ip]['country'] ?? '' and gets an empty string for "unknown".

/**
 * @param PhoenixSettings $settings
 * @param list<string> $ips
 * @return array<string, array{country: string, name: string}>
 */
function stats_geo_lookup_batch(array $settings, array $ips): array
{
    if (
        $settings['stats_geo'] !== true ||
        $ips === [] ||
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
            require_once __DIR__.'/phoenix.hook.event.php';
            phoenix_hook_event('error', ['throwable' => $e, 'source' => 'stats_geo_lookup_batch']);
        }

        return [];
    }

    $geo_reported = false;
    $map = [];
    foreach (array_unique($ips) as $ip) {
        if ($ip === '') {
            continue;
        }

        try {
            // The raw record, not GeoIp2's model graph. With ext-maxminddb the
            // lookup itself costs ~6us, and building Country/Continent/Traits
            // objects around it costs more than the lookup did — measured at
            // 66k/sec through GeoIp2\Database\Reader against 154k/sec here.
            //
            // An address the database does not carry returns null rather than
            // throwing, and one carrying only `registered_country` has no
            // `country` key — GeoIp2 reports that as a null isoCode, so both
            // agree it is unknown.
            $record = $reader->get($ip);
            if (! is_array($record)) {
                continue;
            }
            $code = strtoupper((string) ($record['country']['iso_code'] ?? ''));
            $name = (string) ($record['country']['names']['en'] ?? '');
        } catch (\InvalidArgumentException) {
            // Not an address this reader can parse; the model reader raised the
            // same for it.
            continue;
        } catch (\Throwable $e) {
            // Unexpected mid-batch error; report once per call to avoid a flood.
            if (! $geo_reported && $settings['report_errors']) {
                require_once __DIR__.'/phoenix.hook.event.php';
                phoenix_hook_event('error', ['throwable' => $e, 'source' => 'stats_geo_lookup_batch']);
                $geo_reported = true;
            }
            continue;
        }

        if ($code === '') {
            continue;
        }

        $map[$ip] = ['country' => $code, 'name' => $name !== '' ? $name : $code];
    }

    return $map;
}
