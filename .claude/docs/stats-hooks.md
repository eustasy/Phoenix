# Stats, hooks & geo enrichment

## Lifecycle hooks (`src/hooks/`)

Operator-customizable scripts the tracker calls at well-defined points via
`phoenix_hook()` (`src/functions/phoenix.hook.php`), which checks `is_readable()`
and `include`s the hook from inside its own scope.

- **Plain `include`, never `include_once`** — hooks fire per event, and FPM
  workers serve many requests per process, so a hook must **not** declare
  functions at top level (it would redeclare-fatal on the second event). Hooks
  are scripts, not function definitions.
- A hook sees exactly `$connection`, `$settings`, `$time`, and `$peer` (the last
  **by reference**, so mutations propagate back).

Shipped hooks:

- `phoenix.download.complete.php`, `phoenix.peer.new.php`,
  `phoenix.peer.stopped.php` — ship with gated stat-tracking logic that
  early-returns unless `$settings['stats_enabled']`.
- `phoenix.peer.access.php`, `phoenix.peer.change.php` — **intentionally empty**.
  They fire on every keepalive announce; logging them would flood the events
  table.

## The events ledger

When `stats_enabled` is on, torrent events are logged to the `<prefix>events`
table (the table exists from install, so enabling is a pure config flip).
**Privacy-preserving by design**: a coarse client label (from the peer_id) and a
minified location (country/continent, from the IP) are derived and stored — the
peer_id and IP themselves are **never** stored.

- `stats_events` selects which events to log (`['completed']` by default;
  `started`/`stopped` are higher volume; `access`/`change` are unsupported — they
  fire on every keepalive).
- `stats_retention` prunes old rows during the regular cleanup (announce-time or
  cron), even if `stats_enabled` was later turned off.

Relevant functions: `stats.client.detect.php`, `stats.client.version.php`,
`stats.log.event.php`, `stats.merge.php`. Models: `event.insert.php`,
`events.clean.php`, `events.geo.counts.php`, `stats.*.php`.

## Geo enrichment

With a MaxMind GeoLite2-Country database and `geoip2/geoip2`, events are tagged
with a coarse country/continent and the admin **Geography** page maps active
peers and completed downloads by country.

- Enable with `stats_geo`. The DB path is `stats_geo_database`; empty
  auto-discovers `/usr/share/GeoIP`, `/var/lib/GeoIP`, then `config/`
  (`stats.geo.database.php`, resolved at bootstrap only when `stats_geo` is on,
  to keep the announce hot path free of file stats).
- Degrades gracefully: with the library or DB missing/unreadable, events still
  log (empty location codes) and Geography shows a "not configured" state.
- "Active peers by country" works as soon as geo is configured; "completed
  downloads by country" fills from the events ledger, so it also needs
  `stats_enabled`.

Functions: `stats.geo.database.php`, `stats.geo.lookup.php`. The shared aggregation
`stats_merge()` powers both the admin Dashboard stats block and the public stats
page — one data model, two views.
