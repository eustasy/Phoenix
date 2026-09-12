# Configuration

Configuration should take place in `config/phoenix.custom.php`, NOT `config/phoenix.default.php`. Phoenix _will_ attempt to use the default configuration if yours is missing.

## Table of Contents

- [Admin password requirements](#admin-password-requirements)
- [Two-Factor Authentication (recommended)](#two-factor-authentication-recommended)
- [Backups (recommended)](#backups-recommended)
- [Cron (automating maintenance)](#cron-automating-maintenance)
- [Stat-Tracking](#stat-tracking)
  - [Geo enrichment](#geo-enrichment)
- [Reverse proxies & client IP address](#reverse-proxies--client-ip-address)
- [Error reporting (optional)](#error-reporting-optional)
- [Recovery: lockout, 2FA, backup restore](./RECOVERY.md)

See also [APACHE.md](./APACHE.md) and [NGINX.md](./NGINX.md) for web server
configuration, and [LIMITS.md](./LIMITS.md) for what the settings here cost at
scale.

## Admin password requirements

The admin password is set in three places — the installer, the **Settings** page's _Change password_ action, and the first-run set-password gate — all sharing one policy (following NIST SP 800-63B: length over composition):

- **At least 12 characters.**
- **At most 72 bytes** — bcrypt (`PASSWORD_DEFAULT`) silently truncates beyond 72 bytes, so a longer passphrase would have its tail ignored.

An optional TOTP second factor can be enrolled alongside the password, and is recommended.

## Two-Factor Authentication (recommended)

The admin panel supports an optional TOTP second factor (the codes from authenticator apps like Google Authenticator or Aegis), on top of the admin password.

1. Run `composer require eustasy/authenticatron` (the QR code needs PHP's `gd` extension; without it the installer falls back to showing the secret and `otpauth://` URL for manual entry).
2. During install, scan the displayed QR code with your authenticator app, then enter a current code to confirm and enable 2FA. Leave the code blank to skip it — the panel stays password-only.

Once enabled, the login page asks for the 6-digit code alongside the password. To recover from a lost authenticator, remove the `$settings['admin_totp_secret'] = '...';` line from `config/phoenix.custom.php`; the panel reverts to password-only and you can re-enrol.

## Backups (recommended)

**Set up the backup cron before you need it.** Nothing schedules itself: a
default install writes a backup only when someone clicks **Backups** in the
admin panel, so an install nobody has visited has no backups at all. Add it to
your crontab (`crontab -e`), adjusting the time and verifying the path:

```cron
30 3 * * * php ~/phoenix/bin/backup-database.php
```

The script is silent on success and prints the error and exits non-zero on
failure, so cron will mail you only when something breaks. It needs the
`mysqldump` binary, `proc_open()`, and a writable backup directory.

| Setting | Default | Notes |
| --- | --- | --- |
| `backup_dir` | `''` | Absolute path; empty means `backups/` in the project root. Keep it outside the web root. |
| `backup_retention` | `30` | Days. Dumps older than this are deleted at the end of each run. `0` keeps everything. |
| `backup_compress` | `true` | Gzips the dump as it streams, to `.sql.gz`, using PHP's zlib — no external binary. Level 1, where a SQL dump reaches roughly 10×. |

`backup_retention` is a rotation, not an archive: at the default 30 days a fault
you do not notice for a month leaves you with nothing but copies of the broken
state. If the data matters, copy the dumps somewhere off the box as well — the
backup directory is on the same disk as the database it protects.

Restoring is covered in [RECOVERY.md](./RECOVERY.md#restore-the-database-from-a-backup).

## Cron (automating maintenance)

Phoenix cleans up after itself either way — the question is _when_. By default
`clean_with_cron` is `false`, and roughly `clean_request_percent` (1%) of
announces pay for a cleanup pass inline. That keeps a zero-configuration install
correct, but it means one unlucky peer in a hundred waits on table maintenance
instead of getting a fast announce.

Setting `clean_with_cron` to `true` moves that work to a schedule and turns the
per-request fallback **off**, so no announce ever pays for it:

```cron
*/15 * * * * php ~/phoenix/bin/clean-database.php
15  3 * * * php ~/phoenix/bin/optimize-database.php
30  3 * * * php ~/phoenix/bin/backup-database.php
```

Adjust the times and verify the paths. `clean-database.php` exits
immediately unless `clean_with_cron` is set, so adding the entry without the
setting does nothing at all — and clearing the setting without removing the
entry leaves the tracker doing no cleanup from either route.

**The two maintenance jobs run on deliberately different schedules.**

`clean-database.php` prunes stale peers, prunes `events` and `task_runs`
past their retention, and refreshes index statistics with `ANALYZE`. All of that
is cheap — an analyze measured 1.6 ms — so it runs often.

`optimize-database.php` runs `OPTIMIZE TABLE`, which on InnoDB is a **full table
rebuild**. It is the only thing that reclaims space from deleted rows: after
deleting 118,800 of 120,000 rows a table still measured 25.7 MB, and `ANALYZE`
left it there while `OPTIMIZE` brought it to 0.2 MB. That makes it worth running
after a bulk deletion and wasteful on a table in steady state, where InnoDB just
reuses the pages its own deletes freed — so daily, not every few minutes. It
ignores `clean_with_cron`; a rebuild never happens at announce time either way.

`CHECK TABLE` is not on either schedule. It is a full scan of every row and
index, and InnoDB verifies page checksums as it reads, so corruption surfaces
during normal use without paying for a scan every few minutes. Run it by hand
from **Utilities → Check** when something looks wrong.

| Setting | Default | Notes |
| --- | --- | --- |
| `clean_with_cron` | `false` | `true` moves cleanup to cron and disables the per-announce fallback. |
| `clean_request_percent` | `1` | Percent of announces that clean inline, when `clean_with_cron` is off. |
| `task_retention` | `0` | Days of task-run history to keep in `task_runs`. `0` keeps everything. |
| `stats_retention` | `0` | Days of `events` history to keep. `0` keeps everything — see below. |

**`stats_retention = 0` means the ledger grows without limit**, and it is the
one table that does. Once it outgrows the database's buffer pool, admin pages
that aggregate over it slow down sharply — [LIMITS.md](./LIMITS.md) has the
measurements. Set a retention you can live with before it gets large, rather
than pruning a million rows in one transaction later.

`OPTIMIZE TABLE` is a full table rebuild on InnoDB, needing free disk equal to
the table's size, which is another reason to run this from cron rather than from
a request.

## Stat-Tracking

Phoenix can log torrent events (completions by default; optionally started/stopped via `stats_events`) to an `events` table. Enable it with `$settings['stats_enabled'] = true;`, or from the **Statistics** section of the installer or the admin **Settings** flags — the table exists from install, so it's just a flag. The ledger is privacy-preserving by design — a coarse client label and minified location are derived from the peer_id and IP, and the event row keeps only those derived codes, never the address itself. (The `peers` table does store each peer's address: that is the swarm index a tracker exists to serve.) See the `stats_*` settings (including `stats_retention`, which prunes old rows) in `config/phoenix.default.php`.

### Geo enrichment

With a GeoLite2 database, events are tagged with a coarse country/continent, and the admin **Geography** page maps active peers and completed downloads by country.

1. Run `composer require maxmind-db/reader`, and install `ext-maxminddb` if your distribution packages it (`apt install php-maxminddb`) — the pure-PHP reader works but is around 40× slower, which matters on a busy tracker.
2. Get a free [GeoLite2-Country database](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data) from MaxMind (their licence forbids Phoenix bundling it). Drop it where Phoenix finds it automatically — `/usr/share/GeoIP/GeoLite2-Country.mmdb` (kept current by MaxMind's `geoipupdate`), `/var/lib/GeoIP/`, or the project's `config/` directory — or set `$settings['stats_geo_database']` to a custom path.
3. Enable it with `$settings['stats_geo'] = true;`, or tick it in the installer / admin Settings — the toggle is greyed out there until both the library and a database are present.

The "active peers by country" map works as soon as geo is configured; "completed downloads by country" fills in from the events ledger as completions are logged, so it also needs `stats_enabled`. Geo degrades gracefully: with the library or database missing or unreadable, events are still logged (just with empty location codes) and the Geography page shows a "not configured" state.

## Reverse proxies & client IP address

Phoenix identifies each peer by its connecting IP, so behind a reverse proxy or CDN it must know which forwarded-address header to trust — otherwise it either sees only the proxy or lets clients spoof their address. By default it trusts **nothing** and uses the direct connection (`REMOTE_ADDR`) only: safe, but wrong behind a proxy. Two settings (both empty and fail-closed by default) control it:

- `$settings['forwarded_headers']` — an ordered list of headers to trust, e.g. `['x-forwarded-for']` or `['cf-connecting-ip']`. Recognised: `x-forwarded-for`, `forwarded` (RFC 7239), `x-real-ip`, `cf-connecting-ip`, `true-client-ip`, and the legacy `client-ip`. List **only** headers your proxy sets and strips from client input.
- `$settings['trusted_proxies']` — CIDR ranges of your proxies. A forwarded header is honoured only when `REMOTE_ADDR` falls inside one of these ranges; chain headers (`X-Forwarded-For` / `Forwarded`) are walked from the right, skipping these ranges, to find the real client.

If `trusted_proxies` is empty, forwarded headers are **not** trusted unless you explicitly set `$settings['trust_any_forwarded'] = true` — which trusts the header from any direct connection and so lets anyone reaching the tracker spoof their address. Leave it off unless you fully control who can connect. Often it is cleaner to let the web server rewrite `REMOTE_ADDR` itself (Apache `mod_remoteip`, Nginx `real_ip`) and leave these empty — see [APACHE.md](./APACHE.md) / [NGINX.md](./NGINX.md).

## Error reporting (optional)

Phoenix can hand server-side failures and uncaught exceptions to an external monitor. Two hooks drive it — `src/hooks/phoenix.init.php` starts the monitor at the top of each request, before the database connects, so even a connection failure is reported; `src/hooks/phoenix.error.php` sends each error. Both ship wired for [Sentry](https://sentry.io) and inert, so a default install reports nothing and costs nothing.

1. Run `composer require sentry/sentry`.
2. Set `$settings['report_errors'] = true;` — this is what makes the hooks fire at all.
3. Set `$settings['sentry_dsn'] = 'https://…';` in `config/phoenix.custom.php`, which is gitignored, so the DSN stays out of your repository and `git pull` upgrades stay clean.

Errors arrive tagged with `phoenix.source` — which part of the tracker failed, e.g. `peer_insert`, `tracker_error`, `shutdown` — and carry the running `phoenix_version` as the release. Reporting is best-effort by design: `phoenix_hook_event()` swallows anything the hooks throw, so a bad DSN or an unreachable Sentry degrades to "no reporting", never a broken tracker.

Optional tuning, all in `config/phoenix.default.php`:

| Setting | Default | Notes |
| --- | --- | --- |
| `sentry_environment` | `'production'` | Tags events; use it to separate staging from live. |
| `sentry_traces_sample_rate` | `0.0` | Performance tracing. See the caveat below. |
| `sentry_profiles_sample_rate` | `0.0` | Fraction of _traced_ requests profiled. Needs `ext-excimer`; inert without it. |
| `sentry_enable_logs` | `false` | Forwards log records through Sentry's logging API. |

**Tracing does nothing yet.** Phoenix starts no transactions or spans, and the bare PHP SDK does not instrument requests on its own — that is a framework-SDK feature. So `sentry_traces_sample_rate` has nothing to sample whatever you set it to, and profiling, being a fraction of tracing, has nothing either. Error reporting is unaffected and works on its own. Both settings exist so the wiring is ready if instrumentation is added; leave them at `0.0` until then, and if you do add it, pick a rate well under `1.0` — announce is the hot path and a modest swarm can drive thousands of requests an hour.
