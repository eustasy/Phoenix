# Configuration

Configuration is a single flat `$settings` array threaded through every function
call.

## The settings model

- `config/phoenix.default.php` is the template holding **every** key with a
  sensible default and a one-line `/* comment */`. Code reads `$settings['key']`
  directly with **no fallback layer**, so every key a new feature reads MUST
  exist here. (Its header says "do not modify" — that's for installs; during
  development this is exactly where you add a new setting.)
- `config/phoenix.custom.php` (gitignored) is the operator override, written by
  the installer as `$settings['key'] = 'value';` lines. The installer only knows
  about the keys it writes; everything else falls back to the default file.
- `settings_load()` loads default then custom and returns the merged array; the
  bootstrap then injects `phoenix_version` (and, in the admin panel,
  `nav_counts`) into `$settings` at runtime — same mechanism, not persisted.

When adding a tunable: add the key + default + comment here, read
`$settings['key']` where needed, and (if operator-relevant) make the installer /
admin Settings page write it.

## Notable keys

Full list with comments is in `config/phoenix.default.php`. Highlights:

**Tracker behavior**
- `open_tracker` — off = closed/private tracker (BEP 27); only registered
  `info_hash`es may announce/scrape.
- `announce_interval` / `min_interval` — client request cadence.
- `default_peers` / `max_peers` — peer-list sizing.
- `default_compact`, `announce_external_ip` (BEP 24).
- `full_scrape` — allow info_hash-less scrapes returning every torrent. **Set
  false on a closed tracker** — full scrape ignores the allowed-torrents filter
  and would expose the whole list.
- `scrape_min_interval` — advertised as BEP 48 `min_request_interval`.
- `random_peers` / `random_limit` — peer-selection randomization.

**Cleanup**
- `clean_with_requests` — % of announces that trigger idle-peer cleanup.
- `clean_with_cron` — move cleanup to cron (`bin/clean-and-optimize.php`) for
  faster responses.
- `task_retention`, `stats_retention`, `backup_rotate` — pruning windows.

**Proxy / IP resolution**
- `forwarded_headers` — ordered list of forwarded-address headers to trust
  (`x-forwarded-for`, `forwarded`, `x-real-ip`, `cf-connecting-ip`,
  `true-client-ip`, `client-ip`); empty (default) trusts none, using `REMOTE_ADDR`
  only. Chain headers are walked right-to-left, skipping `trusted_proxies`, so an
  appending proxy can't be spoofed. Handles IPv4 and IPv6 (incl. bracketed forms).
- `trusted_proxies` — CIDR ranges a forwarded header is honored from (the direct
  `REMOTE_ADDR` must fall inside one).
- `allow_any_proxy` — let an empty `trusted_proxies` still trust forwarded headers
  from *any* peer (insecure opt-in; default false — closes the old fail-open).
- `external_ip` — allow clients to specify their IP (`?ip` / `?ipv4` / `?ipv6`).
- `reject_private_ips` — drop RFC 1918 / reserved addresses from the swarm.

**Public index**
- `public_index`, `index_show_meta`, `announce_url` (first tracker in index
  magnet links).

**API** (see [http-api.md](http-api.md))
- `api_keys` — `'user' => 'key'` pairs; `'*'` user is the admin. Empty disables.
- `api_allow_delete` — allow deletion via the API (admin always can).
- `torrent_upload_max` — max `.torrent` upload size for server-side parsing.

**Admin / 2FA** (see [http-api.md](http-api.md))
- `admin_password` (bcrypt; empty = no auth), `admin_login_delay` /
  `admin_login_delay_max`, `admin_totp_secret`, `admin_peers_limit`.

**Stats / geo** (see [stats-hooks.md](stats-hooks.md))
- `stats_enabled`, `stats_events`, `stats_geo`, `stats_geo_database`.

**Database**
- `db_host`/`db_user`/`db_pass`/`db_name`/`db_prefix`, `db_persist` (the `p:`
  gotcha — see [database.md](database.md)), `db_reset`.

**Logging / backups**
- `debug` (NEVER in production — `display_errors` corrupts bencode; errors are
  always logged regardless), `error_log`, `backup_dir`.
