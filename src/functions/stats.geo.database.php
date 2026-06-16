<?php

declare(strict_types=1);

////	stats_geo_database
// Resolve the GeoLite2 country database path. An explicit
// $settings['stats_geo_database'] is honoured first (when readable); otherwise
// the standard locations are searched so dropping the file in a conventional
// spot is found automatically — the geoipupdate system paths, then the
// in-project config/ directory. Returns the first readable path, or '' when no
// database is available (callers treat '' as geo-off). MaxMind's licence
// forbids redistributing the database, so it is never shipped.

/** @param PhoenixSettings $settings */
function stats_geo_database(array $settings): string
{
    $candidates = [];
    if ($settings['stats_geo_database'] !== '') {
        $candidates[] = $settings['stats_geo_database'];
    }
    $candidates[] = '/usr/share/GeoIP/GeoLite2-Country.mmdb';
    $candidates[] = '/var/lib/GeoIP/GeoLite2-Country.mmdb';
    $candidates[] = __DIR__.'/../../config/GeoLite2-Country.mmdb';

    foreach ($candidates as $path) {
        if (is_readable($path)) {
            return $path;
        }
    }

    return '';
}
