# Phoenix — Codebase Scan & Recommendations

_Review of Phoenix v4.0beta2 — 2026-05-30; revised 2026-06-02._

## Scope

A full read-through of the tracker (procedural PHP, ~11k LOC): every entry point,
controller, model, view, function, the SQL schema, config, `bin/` scripts, tests, and
CI. The lens is security, correctness, protocol conformance, and maintainability. This
document is advisory — it recommends, it does not change behaviour.

## Overall assessment — this is a high-quality codebase

The puff-style layout is applied consistently, the MVC split (entry → controller →
model/view) is disciplined, and the fundamentals are genuinely well done:

* `maybe_binary_to_hex()` hex-validates `info_hash`/`peer_id`; `peer_parse_announce_optional()`
  `intval()`s every numeric field; IPs go through `filter_var()`. **No SQL injection found.**
* `install_build_config()` uses `var_export()` — config generation is injection-safe.
* Admin auth hardens the session cookie (`httponly`, `SameSite=Lax`, conditional
  `secure`), calls `session_regenerate_id(true)` on login (anti-fixation), and makes
  logout POST-only (anti-CSRF). `SameSite=Lax` also covers most action-CSRF.
* View output escapes correctly: `html.index`/`xml.index` `htmlspecialchars()` the
  free-text `name`; errors and admin messages are escaped; JSON via `json_encode`.
* `public/scrape.php` carefully prevents an invalid `info_hash` from falling through
  into a full scrape, and closed-tracker filtering errors when nothing is allowed.
* `php -l` is clean on all files; CI runs phpstan (level 9) + php-cs-fixer on PHP,
  sqlfluff on SQL, and markdownlint on docs via qlty, plus PHPUnit on 8.2–8.6 against
  MariaDB with coverage upload. Unit coverage is broad (82 test classes).
* `bin/backup-database.php` is careful: 0600 credentials file, `proc_open` arg-array
  (no shell), strips the persistent `p:` prefix, rotates by age.

The items below are improvements, not emergencies. There is **no correctness bug** in
the announce/scrape flows.

---

## P1 — Security / hardening

### 1.1 Empty `admin_password` ⇒ the admin panel is unauthenticated  _(addressed — 2026-06-03)_

`admin_login_controller()` skips auth when `admin_password` is empty. Rather than fail
closed at runtime (which would lock out a deliberately password-less, hand-rolled config),
the **installer now requires a non-empty admin password** (`admin_install_controller`
rejects the submission and re-prompts otherwise; `install_sanitize_post` already bcrypt-
hashes it). A normal setup therefore can't finish without one, so the only password-less
state is the pre-setup first run — which is installer mode itself, not the live panel. The
documented "empty = no auth" escape hatch remains for operators who intentionally run
without auth via a manual `phoenix.custom.php`.

* Files: `src/controller/admin.install.php`, `src/views/html.install.php`.

### 1.2 The SQL layer has no defense-in-depth  _(addressed — 2026-06-03)_

Previously every query was string-concatenated, with safety resting on an unenforced
invariant ("every value reaching SQL is pre-sanitised"). The queries that carry
client-supplied values now bind them as statement parameters via `mysqli_execute_query()`
(PHP 8.2+): the hot-path writes (`peer_insert`, `peer_update`, `peer_delete`,
`torrent_increment_downloads`), the announce reads (`peer_select`, `peers_count_swarm`,
`peers_count_rate`, `peers_select_active`), and the scrape reads (`peers_scrape` /
`torrents_scrape`, via a parameterized `scrape_build_where_clause`). Table/column names
stay interpolated (operator config, not bindable); `LIMIT` stays interpolated with an
integer narrowing, since mysqli can't bind a string LIMIT.

* **Remaining (low priority, no client exposure):** the cron/cleanup DELETEs, stats,
  allowed/listed, and `task_log` still interpolate — but only `db_prefix` + integers /
  internal constants, never client input, so there's no injection surface to close.
* Files: `src/model/*.php`, `src/functions/scrape.build.where.clause.php`,
  `src/controller/scrape.specific.php`.

### 1.3 No brute-force throttle on admin login  _(addressed — 2026-06-03)_

Failed admin logins now incur an escalating, capped, per-session delay
(`auth_login_throttle_delay`): the delay grows with the consecutive-failure count held
in the session and is capped at `admin_login_delay_max` (`admin_login_delay` is the base;
0 disables). A successful login clears the counter. This is a per-session backoff — a
cookie-less attacker still pays the base delay on each request — and complements the
per-IP proxy rate-limiting documented in `APACHE.md` / `NGINX.md` (still the recommended
primary defense; see P1.6).

* Files: `src/controller/admin.login.php`, `src/functions/auth.login.throttle.delay.php`,
  `config/phoenix.default.php`.

### 1.4 All runtime errors are silenced  _(addressed — 2026-06-03)_

`src/phoenix.php` previously set `error_reporting(0)`, so a fatal yielded a blank 200
with nothing logged. The bootstrap now sets a safe baseline before settings load (log on,
display off, `error_reporting(E_ALL & ~E_DEPRECATED)`), and `error_configure()` layers two
operator knobs: `error_log` (redirect PHP's log to a file) and `debug` (raise verbosity +
display errors for local troubleshooting only — never in production, as display corrupts
bencode responses). The DB layer's `@`-suppression remains, but connection failures are
already surfaced via `tracker_error()`.

* Files: `src/phoenix.php`, `src/functions/error.configure.php`, `config/phoenix.default.php`.

### 1.5 `Access-Control-Allow-Origin: *` on every response  _(addressed — 2026-06-03)_

The header was emitted from the shared bootstrap, so admin responses carried it too.
It now lives in the public read entry points (`public/announce.php`, `public/scrape.php`,
`public/index.php`), sent before bootstrap so error responses still carry it, and is no
longer sent on the admin endpoint.

* Files: `src/phoenix.php`, `public/announce.php`, `public/scrape.php`, `public/index.php`.

### 1.6 Client-controlled source IPs — defaults safe; reinforced  _(reinforced — 2026-06-03)_

`external_ip` and `honor_xff` both default to `false`, and `honor_xff` carries an inline
warning about trivial spoofing. When enabled, a client can set the address handed to the
swarm (peer-list poisoning / reflective DDoS) and evade the IP-based rate limiter.

`reject_private_ips` (default `true`) now drops private (RFC 1918 / ULA `fc00::/7`) and
reserved addresses during peer resolution, so they can no longer be injected into the
swarm or used to dodge the rate limiter, and a private `REMOTE_ADDR` (NAT/proxy) falls
through to a public client-declared IP per BEP 3.

* **Done (2026-06-03):** `APACHE.md` / `NGINX.md` now document running behind a proxy —
  prefer the server's real-IP module (`mod_remoteip` / `ngx_http_realip_module`) with
  `honor_xff = false`, and if `honor_xff` is enabled the proxy must overwrite
  `X-Forwarded-For` so a client cannot supply it.
* **Also done (2026-06-03):** `peer_address_candidates()` gates `honor_xff` on a
  configurable `trusted_proxies` CIDR list (`ip_in_cidr`): `X-Forwarded-For` is honored
  only from connections within those ranges — so a bypassed/misconfigured proxy can't be
  spoofed via a direct connection — with empty meaning trust any peer (for proxies that
  have no stable range).
* Files: `APACHE.md`, `NGINX.md`, `src/functions/peer.address.candidates.php`,
  `src/functions/ip.in.cidr.php`, `config/phoenix.default.php`.

---

## P2 — Correctness / robustness

* **No correctness bug found.** The announce event machine
  (`peer_select` → `peer_changed` → insert/update; `completed` →
  `torrent_increment_downloads` + state; `stopped` → delete) is sound, and swarm
  counts come from a dedicated `peers_count_swarm()` query, not the truncated peer list.
* **Stale/contradictory comment** in `public/admin.php` _(addressed — 2026-06-03)_ — the
  old "not secure / should not be deployed" note now accurately describes the bcrypt auth,
  hardened session, and login throttle, and notes that auth is skipped when
  `admin_password` is empty (see P1.1).
* **`full_scrape` defaults to `true`** _(addressed — 2026-06-03)_ — every torrent's stats
  are publicly scrapable by default, and a full scrape ignores the allowed-torrents filter
  (`scrape_full_controller` → `*_scrape_all`). `config/phoenix.default.php` now documents
  this and tells closed/private-tracker operators to set it `false`.

## P3 — Protocol conformance

* Compact (BEP 23 / BEP 7) + non-compact peer dicts, `min interval`, and bencoded
  `failure reason` are all present and look conformant. No action needed.

## P4 — Maintainability

* **Psalm taint audit** _(done — 2026-06-03)_ — ran `psalm --taint-analysis` (Psalm 5.26,
  standalone; qlty has no Psalm plugin, no standing config added). Result: **no SQL taint
  and no tainted HTML reaching the browser** — the P1.2 parameterization and the view
  escaping (`htmlspecialchars` / `xml_escape`) hold. Psalm reported exactly one flow, a
  **false positive**: `$_POST` → the installer's `install_build_config()`, which renders
  values with `var_export()` (injection-safe PHP literals — see the "Overall assessment").
  Psalm doesn't model `var_export()` as a taint-escape; if Psalm is ever adopted, a
  `@psalm-taint-escape html` on that function clears it.
* **XML/HTML are hand-assembled** (string concat) — safe (non-`name` fields are hex/int).
  _(addressed — 2026-06-03)_ The XML views now route free text through a single
  `xml_escape()` helper (`ENT_QUOTES | ENT_XML1`, attribute-safe), mirroring
  `bencode_encode()` as the one emitter — so element text (and any future attribute
  output) escapes consistently instead of via ad-hoc `htmlspecialchars()` calls.
* (No dead code confirmed — an earlier automated guess flagging `parse_ipv4/6` was a
  false positive; both are used via `peer_resolve_addresses`.)

## P5 — Testing / CI

* Unit coverage is strong (82 test classes); clover coverage is uploaded to qlty on 8.6.
* **End-to-end smoke tests** _(addressed — 2026-06-03)_ — `tests/smoke/` drives every
  `public/*.php` entry point over real HTTP (`php -S`) through the full deployment
  lifecycle (install → announce → scrape → index → admin login → magnet), in a new
  `smoke-php.yml` workflow. Coverage is collected in the server via PCOV
  (`coverage-prepend.php`), merged to Clover (`merge-coverage.php`), and uploaded to qlty,
  which unions it with the unit clover; `public/` was added to the coverage `<source>` so
  the entry-point glue now counts.

---

## Suggested sequencing (when acting on this)

All P1–P5 items are addressed (P3 needed no action). Remaining options are nice-to-haves:
the `honor_xff` trusted-proxy CIDR gating is done, and the Psalm taint audit was run once
(§P4); a recurring Psalm gate was intentionally not added.

## Verification (for any future change)

* `composer install && vendor/bin/phpunit` (needs reachable DB + `phoenix.custom.php`).
* `qlty check --all` (phpstan + php-cs-fixer + sqlfluff + markdownlint).
* Manual: deploy with empty `admin_password` → panel must deny (1.1); forced error with
  logging on → entry in error log, client still gets clean output (1.4).
