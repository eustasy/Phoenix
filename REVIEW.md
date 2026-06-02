# Phoenix — Codebase Scan & Recommendations

_Point-in-time review of Phoenix v4.0beta2 — 2026-05-30._

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
* `php -l` is clean on all files; CI lints PHP/SQL/JSON/XML and runs PHPUnit on 8.2–8.6
  against MariaDB with coverage upload. Unit coverage is broad (~90 test classes).
* `bin/backup-database.php` is careful: 0600 credentials file, `proc_open` arg-array
  (no shell), strips the persistent `p:` prefix, rotates by age.

The items below are improvements, not emergencies. There is **no correctness bug** in
the announce/scrape flows.

---

## P1 — Security / hardening

### 1.1 Empty `admin_password` ⇒ the admin panel is unauthenticated  _(confirmed)_

`admin_login_controller()` returns `null` (skips auth) when the password is empty;
`config/phoenix.default.php` documents this (`empty = no auth`) and warns to set it or
delete `admin.php`. But it fails **open**: an operator who skips the warning exposes
`clean`, `optimize`, and `setup` — and `setup` can **DROP/recreate the tables** when
`db_reset` is true.

* **Fix:** fail closed — when `admin_password` is empty, render a "set an admin
  password" notice and refuse the panel/actions instead of granting access.
* Files: `src/controller/admin.login.php`, `config/phoenix.default.php`.

### 1.2 The SQL layer has no defense-in-depth  _(confirmed — architectural)_

There are **zero** `mysqli_real_escape_string` calls and no prepared statements;
every query is string-concatenated. It's safe _today_ only because every interpolated
value is hex-validated (`info_hash`/`peer_id`), `intval()`-cast (ports, `left`,
`uploaded`, `downloaded`, `numwant`, timestamps), a validated IP, or operator config
(`db_prefix`/`db_name`). The invariant "every value reaching SQL is pre-sanitised" is
load-bearing and unenforced — one future field added to an `INSERT`/`WHERE` without
sanitising is an injection.

* **Fix (pick one or both):** migrate the hot-path writes (`peer_insert`,
  `peer_update`, `peer_delete`, `torrent_increment_downloads`) and the scrape/count
  reads to `mysqli_prepare`/`bind_param`; and/or add static analysis (see P4) to catch
  unsanitised interpolation. Prepared statements are the durable fix.
* Files: `src/model/*.php`.

### 1.3 No brute-force throttle on admin login  _(confirmed)_

A single shared password with unlimited attempts. The project already has a rate-limit
pattern (`announce_check_rate_limit` / `peers_count_rate`).

* **Fix:** per-IP attempt backoff/lockout, or a fixed delay on failure.
* File: `src/controller/admin.login.php`.

### 1.4 All runtime errors are silenced  _(confirmed — operability/security)_

`src/phoenix.php` sets `error_reporting(0)` and the DB layer uses `@`-suppression, so a
fatal yields a blank 200 with nothing logged.

* **Fix:** keep `display_errors` off but set `log_errors = 1` (to a file), or add a
  `debug` setting that flips verbosity.
* File: `src/phoenix.php`.

### 1.5 `Access-Control-Allow-Origin: *` on every response  _(confirmed — low)_

Set globally in the bootstrap, so admin responses carry it too. Low risk (no
`Allow-Credentials`, `SameSite=Lax`), but unnecessary on admin.

* **Fix:** scope the header to the announce/scrape endpoints.
* File: `src/phoenix.php`.

### 1.6 Client-controlled source IPs — defaults are safe; reinforce  _(confirmed — low)_

`external_ip` and `honor_xff` both default to `false`, and `honor_xff` carries an
inline warning about trivial spoofing — good. When enabled, a client can set the
address handed to the swarm (peer-list poisoning / reflective DDoS) and evade the
IP-based rate limiter.

* **Fix:** reinforce the trusted-proxy requirement in `APACHE.md`/`NGINX.md`; consider
  gating `honor_xff` behind a configurable trusted-proxy CIDR rather than blanket trust.
* File: `src/functions/peer.address.candidates.php`, docs.

---

## P2 — Correctness / robustness

* **No correctness bug found.** The announce event machine
  (`peer_select` → `peer_changed` → insert/update; `completed` →
  `torrent_increment_downloads` + state; `stopped` → delete) is sound, and swarm
  counts come from a dedicated `peers_count_swarm()` query, not the truncated peer list.
* **Stale/contradictory comment:** `public/admin.php` says _"This page is not secure.
  It should not be deployed in a production environment."_ despite real auth existing —
  clarify or remove so operators aren't misled. _(confirmed)_
* **`full_scrape` defaults to `true`:** every torrent's stats are publicly scrapable by
  default. Conventional for open trackers, but closed-tracker operators should know to
  flip it. _(confirmed — minor)_

## P3 — Protocol conformance

* Compact (BEP 23 / BEP 7) + non-compact peer dicts, `min interval`, and bencoded
  `failure reason` are all present and look conformant. No action needed.

## P4 — Maintainability

* **Add PHPStan or Psalm to composer + CI** — highest-leverage change. The procedural,
  array-shaped-data style is exactly what static analysis guards best (undefined keys,
  undefined functions, typos), and it directly backstops the SQL invariant in P1.2.
* **XML/HTML are hand-assembled** (string concat). Safe today (non-`name` fields are
  hex/int; `name` is escaped). Optional: a small xml-escape helper for consistency,
  mirroring `bencode_encode` as the single bencode emitter.
* **`CLAUDE.md` commit trailer** pins `Co-Authored-By: Claude Sonnet 4.6` — update to
  the current model for consistency.
* (No dead code confirmed — an earlier automated guess flagging `parse_ipv4/6` was a
  false positive; both are used via `peer_resolve_addresses`.)

## P5 — Testing / CI

* Unit coverage is strong; clover coverage is uploaded to qlty on 8.6.
* **Gap:** no end-to-end smoke test driving `public/announce.php` / `public/scrape.php`
  over HTTP (controllers are unit-tested, which covers most logic). Consider one
  integration smoke test per endpoint.
* Fold the P4 PHPStan step into `.github/workflows/normal.yml`.

---

## Suggested sequencing (when acting on this)

1. P1.1 (fail-closed empty password) + P1.4 (error logging) — small, high value.
2. P4 PHPStan in CI — cheaply surfaces more and guards P1.2.
3. P1.2 prepared statements on hot-path queries.
4. P1.3 login throttle, P1.5 ACAO scope, P1.6 docs.
5. P2 comment fix, P5 smoke tests, trailer cleanup.

## Verification (for any future change)

* `composer install && vendor/bin/phpunit` (needs reachable DB + `phoenix.custom.php`).
* `./.normal/check-php.sh`, `sql-lint .`; if added, `vendor/bin/phpstan analyse`.
* Manual: deploy with empty `admin_password` → panel must deny (1.1); forced error with
  logging on → entry in error log, client still gets clean output (1.4).
