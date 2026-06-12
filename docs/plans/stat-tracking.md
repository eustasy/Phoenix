# Plan: Stat-Tracking (events table + opt-in hooks)

## Goal

Reintroduce the event logging **bits** had — an `events` ledger enriched with the
BitTorrent client and a *minified* geo location — but in a clean v4-native format
that is **disabled by default**, privacy-preserving, and degrades gracefully when
GeoIP is unavailable.

bits logged only completions via a filled-in `phoenix.download.complete` hook,
storing `time, user, torrent, event, client, country, continent` and never storing
raw IPs or peer_ids. We keep that privacy posture and generalise it behind a setting
gate.

## Decisions (locked)

* **Toggle:** a single settings gate, `$settings['stats_enabled']` (default
  `false`). Shipped hooks early-`return` unless it is on; the `events` table is only
  created when stats are enabled.
* **Event fields:** `client` + minified geo (`country`/`continent`) + torrent
  `user` (owner) — full bits field parity. Never store raw IP or peer_id.
* **GeoIP:** optional Composer dependency, **off by default**, and auto-disabled
  when the library or database file is absent.

## Schema — new `sql/events.sql`

"Similar but new format" vs bits: 64-bit id, fixed-width ISO code columns with `''`
defaults, and indexes that actually serve the analytics queries.

```sql
CREATE TABLE IF NOT EXISTS `phoenix_events` (
 `id`        bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 `time`      int(10) unsigned    NOT NULL,
 `info_hash` varchar(40)         NOT NULL,
 `event`     varchar(16)         NOT NULL,
 `client`    varchar(64)         NOT NULL DEFAULT '',
 `user`      varchar(12)         NOT NULL DEFAULT '',
 `country`   char(2)             NOT NULL DEFAULT '',
 `continent` char(2)             NOT NULL DEFAULT '',
 PRIMARY KEY (`id`),
 KEY `time` (`time`),
 KEY `info_hash` (`info_hash`)
) ENGINE = MyISAM DEFAULT CHARSET = latin1;
```

Differences from bits' `events`:

* `bigint` id (bits used `int(12)`; the dump was already at ~1.7M rows).
* `country`/`continent` are `char(2)` ISO codes with `''` default, not `varchar(12)`.
* Added `KEY time` + `KEY info_hash` for `downloads_in_period` / per-torrent rollups.
* **No** `ip`, `peer_id`, or any column that identifies a person. `client` is a
  coarse label; `user` is the torrent owner, not the peer.

### Creation / migration

* `db_create()` (`src/model/db.create.php`): append `'events'` to `$tables`
  **only when `$settings['stats_enabled']`** is true. This keeps the table out of
  installs that don't use it.
* Add an admin **"Create stats table"** action (or fold into the schema-upgrade
  action from the meta-index plan) so an operator who flips `stats_enabled` on an
  existing install can create the table without a full reinstall. `sql/events.sql`
  is also importable manually.

## Settings (`config/phoenix.default.php`)

```php
//// Stat-Tracking Options
/* log torrent events (completions, etc.) to the events table; off by default */
$settings['stats_enabled'] = false;
/* which announce events to log. 'completed' matches bits; */
/* 'started'/'stopped' are higher-volume. 'access'/'change' are intentionally */
/* unsupported here — they fire on every keepalive and would flood the table. */
$settings['stats_events'] = ['completed'];
/* enrich events with a minified geo location (country + continent only). */
/* requires the geoip2 library and a GeoLite2 database (see below). */
$settings['stats_geo'] = false;
/* absolute path to a MaxMind GeoLite2-Country.mmdb; empty = geo disabled. */
/* not shipped — MaxMind's license forbids redistribution; operator downloads. */
$settings['stats_geo_database'] = '';
```

## GeoIP dependency (optional, graceful)

* Add `geoip2/geoip2` to `composer.json` under **`suggest`** (not `require`) so the
  tracker core stays dependency-light and installs that don't use stats pull nothing
  extra. Document `composer require geoip2/geoip2` in the stats docs.
* The **GeoLite2 `.mmdb` is never committed** (MaxMind license). Operator downloads
  it and points `stats_geo_database` at it.
* Geo is active only when **all** hold: `stats_geo === true` **and**
  `class_exists(\GeoIp2\Database\Reader::class)` **and**
  `is_readable($settings['stats_geo_database'])`. If any is false, geo is silently
  skipped and `country`/`continent` stay `''` — "disable GEO if not available".
* The lookup catches `GeoIp2\Exception\AddressNotFoundException` (and any reader
  error) and returns empty codes rather than failing the announce. Geo enrichment
  must **never** break a tracker response.

## Functions / model (one per file, puff-style)

* `src/functions/stats.client.detect.php` → `stats_client_detect(string $peer_id): string`
  Maps a peer_id prefix to a client label (Azureus-style `-XX1234-` and Shadow's
  style). Pure, table-driven, easily unit-tested. Peer_id is used only to derive the
  label — never stored.
* `src/functions/stats.geo.lookup.php` → `stats_geo_lookup(array $settings, string $ip): array`
  Returns `['country' => '..', 'continent' => '..']`, or `['country' => '',
  'continent' => '']` when geo is disabled/unavailable/not found. Encapsulates all
  the availability checks above.
* `src/model/event.insert.php` → `event_insert(mysqli $connection, array $settings, array $event): bool`
  Prepared-statement INSERT into `<prefix>events`. (info_hash is already
  hex-sanitized upstream, but use `mysqli_execute_query` bind params like
  `torrent_add` for the free-text `client` field.)
* `src/model/torrent.user.php` → `torrent_user(mysqli $connection, array $settings, string $info_hash): string`
  Looks up the owning `user` from `torrents` (bits did this inline in the hook).
  Returns `''` when the torrent has no owner.

## Hooks (`src/hooks/`)

Replace the empty stubs with real, gated logic. Each begins with the gate so a
disabled install pays almost nothing:

```php
if (empty($settings['stats_enabled'])) {
    return; // returns from the include back into phoenix_hook()
}
```

* `phoenix.download.complete.php` — the primary logger (bits parity). Resolves
  `event` from `$_GET['event']`, `client` via `stats_client_detect($peer['peer_id'])`,
  `user` via `torrent_user(...)`, geo via `stats_geo_lookup($settings, $peer['ipv4']
  ?: $peer['ipv6'])`, then `event_insert(...)`. Only logs if `'completed'` is in
  `$settings['stats_events']`.
* `phoenix.peer.new.php` / `phoenix.peer.stopped.php` — optional `'started'` /
  `'stopped'` logging, each gated on membership in `$settings['stats_events']`
  (empty by default for these two).
* `phoenix.peer.access.php` / `phoenix.peer.change.php` — **left as no-op stubs.**
  They fire on every keepalive announce; logging them would dwarf the meaningful
  events. Documented as intentionally unsupported.

The hook contract is unchanged: `phoenix_hook()` already passes `$connection`,
`$settings`, `$time`, and `$peer` (by ref) into the include's scope, and the
controller already calls `phoenix_hook('download.complete', ...)` after the download
count is incremented (`src/controller/announce.php:124`).

## Privacy summary

* Stored: coarse `client` label, `country`/`continent` ISO codes, torrent `user`
  (owner, not peer), `time`, `info_hash`, `event`.
* Never stored: raw IP, peer_id, port. IP is used transiently for the geo lookup and
  discarded; peer_id is used transiently for client detection and discarded.
* Everything is opt-in: no table without `stats_enabled`; no geo without
  `stats_geo` + library + database.

## Tests (`tests/phoenix/`)

* `StatsClientDetectTest` — known peer_id prefixes → labels; unknown → sensible
  default.
* `StatsGeoLookupTest` — with geo **disabled / library absent / db path missing**,
  returns empty codes and never throws (this path needs no GeoLite2 file, so it runs
  in CI). If a fixture mmdb is available, assert a known IP resolves.
* `EventInsertTest` — inserts and reads back a row (uses the `TESTING_` prefix); the
  events table is created in the test bootstrap when `stats_enabled` is forced on for
  the test DB.
* Hook gating — with `stats_enabled = false`, completing a download writes **no**
  events row.

## Touched files (summary)

* `sql/events.sql` (new)
* `src/model/db.create.php` (conditionally add `events`), `event.insert.php`,
  `torrent.user.php` (new)
* `src/functions/stats.client.detect.php`, `stats.geo.lookup.php` (new)
* `src/hooks/phoenix.download.complete.php`, `phoenix.peer.new.php`,
  `phoenix.peer.stopped.php`
* `config/phoenix.default.php`
* `composer.json` (`suggest: geoip2/geoip2`)
* `src/controller/admin.*` (create-table / schema action), `public/admin.php`
* docs (stats setup: composer require, GeoLite2 download, settings), `tests/phoenix/*`
