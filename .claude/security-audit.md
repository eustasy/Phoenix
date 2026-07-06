# Phoenix Tracker — Security Audit Report

**Date:** 2026-07-04
**Target:** Phoenix BitTorrent tracker (procedural PHP 8.2–8.6, mysqli, no framework)
**Scope:** Static analysis + automated tooling **and** dynamic testing against a live
instance. `public/`, `src/`, `config/`, `sql/`, deployment guides.
**Method:** Manual vulnerability-class review; Semgrep 1.168 (`p/php`, `p/owasp-top-ten`,
`p/security-audit`, 117 rules × 244 files); live adversarial testing against a `php -S`
instance backed by a throwaway MariaDB 11; `composer audit`.
**Deliverable:** Findings only — no code changed *(original engagement, 2026-07-04)*.
**Remediation status (updated 2026-07-06):** #3, #4, #6, #7, #8 fixed, plus related hardening
(admin password rehash-on-login, an error-reporting/monitoring facility, and an XML/JSON
content-type & charset fix), plus #5 in part (API keys now hashed at rest). #1 and #2 remain
open. See the Status column, the per-finding Status lines, and the Remediation Log below.

---

## Executive summary

Phoenix is in **notably good shape** for a hand-rolled procedural-PHP application. The
classic web-app vulnerabilities are absent by construction: the database layer is fully
parameterized (no SQL injection), output is escaped at render time (no XSS), CSRF is
enforced on every state-changing endpoint, and the bencode parser is bounded against
resource exhaustion. Production has **zero third-party dependencies**, so there is no
supply-chain surface, and `composer audit` is clean.

The findings below are therefore concentrated not in the request-handling core but in
**first-run / configuration / operational** surfaces: an unauthenticated first-run
installer, a couple of config-dependent footguns (XFF trust, empty admin password), one
genuine uncaught-exception bug on the announce hot path, and some defense-in-depth gaps
(plaintext API keys at rest, missing security headers). None are remote code execution or
data-exfiltration-by-default.

| # | Severity | Finding | Auth required | Status |
|---|----------|---------|---------------|--------|
| 1 | High     | Unauthenticated first-run installer takeover | none (pre-install) | ⬜ Open |
| 2 | Medium   | Unauthenticated SSRF + error disclosure via installer DB test | none (pre-install) | ⬜ Open |
| 3 | Medium   | X-Forwarded-For IP spoofing (honor_xff + empty trusted_proxies) | none | ✅ Fixed |
| 4 | Medium   | Uncaught DB exception → HTTP 500 on announce (`left` omitted/negative) | none | ✅ Fixed (`647cf39`) |
| 5 | Low      | API keys / TOTP secret stored plaintext at rest | n/a | ◐ Partial |
| 6 | Low      | Open redirect via `REQUEST_URI` in admin login redirect | post-login | ✅ Fixed (`2671e33`) |
| 7 | Low      | Missing HTTP security headers (CSP / XFO / nosniff) | n/a | ✅ Fixed (`aadcca0`) |
| 8 | Low      | Empty `admin_password` disables admin auth entirely | n/a | ✅ Fixed |

Plus informational hardening notes and a summary of the (substantial) things done right.

---

## Remediation log

Fixes applied since this report (newest first; git history has the full diffs):

- **#5 — API keys stored in plaintext at rest** — FIXED (the hashable half). API keys are now
  stored as SHA-256 hashes: `api_authenticate_key()` hashes the presented key and compares with
  `hash_equals`. A new admin **API Keys** page (create / rotate / revoke) generates a key, shows
  it once, and persists only its hash via `config_write` — no plaintext ever lands in the config.
  The TOTP secret and `db_pass` inherently must stay reversible, so they remain plaintext
  (mitigate with `chmod 600`). Breaking: existing plaintext `api_keys` must be re-issued.
- **#3 — X-Forwarded-For / forwarded-header IP spoofing** — FIXED. Replaced `honor_xff` (bool)
  with `forwarded_headers` (an ordered list of headers to trust; empty by default = use
  `REMOTE_ADDR` only), added `allow_any_proxy` (default false) so an empty `trusted_proxies`
  fails **closed** instead of trusting everyone, and resolve the chain headers (X-Forwarded-For,
  RFC 7239 Forwarded) with a rightmost-untrusted-hop walk so an appending proxy can't be
  spoofed. Adds X-Real-IP, CF-Connecting-IP, True-Client-IP and the legacy Client-IP as
  selectable headers; all IPv4/IPv6-aware. APACHE.md / NGINX.md updated.
- **#8 — Empty `admin_password` disables admin auth** — FIXED. When `admin_password` is empty,
  `admin.php` now serves a one-time "set admin password" gate (shared `auth_password_valid`
  policy: ≥12 characters, ≤72 bytes; optional TOTP enrolment mirroring the installer) and
  persists via `config_write`, instead of the unauthenticated panel. A new `admin_auth_optional`
  setting (default false) lets an operator knowingly run the panel unauthenticated (e.g. behind
  reverse-proxy auth). The 12/72 policy is applied consistently at the installer, the Settings
  change-password action, and the gate.
- **#7 — Missing HTTP security headers** — FIXED (`aadcca0`). A new `http_security_headers()`
  helper emits `X-Content-Type-Options: nosniff` on every response, plus a per-group set:
  `X-Frame-Options` (`DENY` on admin/api, `SAMEORIGIN` on public HTML), `Referrer-Policy`,
  `Cache-Control: no-store` on admin/api, and a Content-Security-Policy on the HTML/API
  surfaces. Independently reviewed (no cross-group leakage; the CSP origin set verified against
  the shipped assets). **Follow-up tracked:** the "strong CSP" — nonces + self-hosting Lucide /
  jsVectorMap / Google Fonts to drop `'unsafe-inline'` and the CDN origins, plus pinning
  `lucide@latest` and adding SRI.
- **XML/JSON content-type & charset** (not an original finding; surfaced during #7) — FIXED
  (`c155579`). XML was served `text/xml; charset=iso-8859-1` — PHP appends the binary protocol's
  `default_charset` to `text/*`, and for `text/xml` the HTTP charset overrides the document's own
  `encoding="UTF-8"` → mojibake on non-ASCII names. Now `application/xml; charset=UTF-8`
  (`application/*` is untouched by `default_charset`); JSON now declares `charset=UTF-8` explicitly.
- **#6 — Open redirect via `REQUEST_URI`** — FIXED (`2671e33`). New `auth_safe_redirect_path()`
  permits only same-origin absolute paths (rejects `//host`, `/\host`, absolute URLs) with a fixed
  `admin.php` fallback; applied to the login and logout redirects.
- **Admin password rehash-on-login** (was an informational hardening note) — FIXED (`e4cfd1f`).
  `auth_rehash_password()` transparently upgrades the stored bcrypt hash via
  `password_needs_rehash()` when `PASSWORD_DEFAULT` moves on; best-effort, never blocks login.
- **Error-reporting / monitoring facility** (new capability) — ADDED (`239ded7`, `7d185e7`,
  `f1bfafc`). A hardened `phoenix_hook_event()` dispatcher + an opt-in `report_errors` setting
  route uncaught exceptions, fatals, non-fatal PHP warnings/notices, caught DB-write exceptions,
  and geo-lookup faults to operator-supplied `src/hooks/phoenix.{init,error}.php` (e.g. Sentry) —
  zero third-party code in core.
- **#4 — Uncaught DB exception → HTTP 500 on announce** — FIXED (`647cf39`, `3c1776a`).
  `peer_insert` / `peer_update` catch the strict-mode exception; the `left` column is signed (via
  migration) so the `-1` "unknown" sentinel round-trips. The sibling **`port`** field is now
  range-validated (out-of-range / negative → `Invalid port.` rather than a swallowed insert).
  Regression tests added.

**Still open:** **#1** (installer takeover) & **#2** (installer SSRF) — both need the installer
gated behind a one-time setup token / CLI. **#5** is now **partial** — API keys are hashed at
rest; the TOTP secret and `db_pass` inherently can't be (mitigate with file permissions).

---

## Findings

### 1. [High] Unauthenticated first-run installer takeover
**Where:** `public/admin.php:36-40`, `src/controller/admin.install.php`
**Class:** Improper access control / installation (CWE-306)
**Status:** ⬜ OPEN — needs the installer gated behind a one-time setup token / CLI. Operationally mitigated by removing or IP-restricting `admin.php` after setup (deployment docs).

When `config/phoenix.custom.php` does not exist, `admin.php` serves the installer
(`admin_install_controller`) with **no authentication**. A single anonymous POST completes
the install: it connects to an attacker-chosen database and writes a `phoenix.custom.php`
containing the attacker's DB credentials and their own bcrypt admin-password hash.

**Exploit scenario.** During the window between deploying the code and the operator
completing setup — or any time the config file is later deleted or lost — an attacker who
reaches `admin.php` first submits the install form pointed at their own DB with a password
only they know. They now own the admin panel and control where the tracker's data flows.
This is the same exposure class as an unprotected WordPress `wp-admin/install.php`.

**Confirmed (live):**
```
POST /admin.php  process=install&db_host=127.0.0.1&db_user=root&db_pass=auditpass
                 &db_name=phoenix&db_prefix=phoenix_&admin_password=### attacker's pass ###
→ HTTP/1.1 302 Found
  Location: admin.php?installed=1
# config/phoenix.custom.php written by the anonymous request:
$settings['admin_password'] = '### attacker's hash ###';
```

**Remediation.** Gate the installer behind a one-time secret: e.g. require a setup token
printed to the server console / written to a root-only file, or drive first-run setup from
a CLI script (`bin/`) rather than an unauthenticated web endpoint. At minimum, document the
race prominently (complete setup immediately; IP-restrict `admin.php` from the first
request, not just post-setup) — the deployment guides already advise removing `admin.php`
after setup, which closes this once setup is done but not before.

---

### 2. [Medium] Unauthenticated SSRF + error disclosure via installer DB test
**Where:** `src/controller/admin.install.php:112-124`
**Class:** Server-side request forgery / information exposure (CWE-918, CWE-209)
**Status:** ⬜ OPEN — closed by the same installer gating as #1.

Still in unauthenticated installer mode, the "test DB connection before writing config"
step calls `@mysqli_connect($test_host, …)` with the attacker-supplied `db_host`, then
reflects the raw connection/resolver error back in the response.

**Exploit scenario.** An anonymous attacker uses the pre-install window to make the server
open outbound MySQL connections to arbitrary hosts and reads the reflected error to
fingerprint the internal network: "Access denied" means a live MySQL is listening there;
"Connection refused" / "Name or service not known" means it is not. The port is fixed at
3306, so this is a MySQL-service internal port scanner plus error harvesting.

**Confirmed (live):**
```
db_host=127.0.0.1        → "Access denied for user 'root'@'172.17.0.1' (using password: YES)"
                            (proves a live MySQL + leaks the connecting internal IP)
db_host=internal.invalid → "php_network_getaddresses: getaddrinfo for internal.invalid failed…"
                            (proves arbitrary outbound name resolution)
```

**Remediation.** Same gating as #1 removes the unauthenticated exposure. Additionally,
return a generic "could not connect" message rather than the raw `mysqli_connect_error()`
string.

---

### 3. [Medium] X-Forwarded-For IP spoofing (honor_xff + empty trusted_proxies)
**Where:** `src/functions/peer.proxy.trusted.php:17-19`, `peer.address.candidates.php:37-55`
**Class:** IP spoofing / trust boundary (CWE-348, CWE-290)
**Status:** ✅ FIXED — `honor_xff` replaced by `forwarded_headers` (empty by default = trust nothing); a new `allow_any_proxy` (default false) gates the empty-`trusted_proxies` case, so it now fails **closed**. Chain headers use a rightmost-untrusted-hop walk (append-proxy safe), and the recognised set (XFF, Forwarded, X-Real-IP, CF-Connecting-IP, True-Client-IP, Client-IP) is operator-selectable and v4/v6-aware. Unit-tested + live-verified.

When `honor_xff = true` and `trusted_proxies = []` (empty), `peer_proxy_trusted()` returns
`true` for **every** connection, so the leftmost `X-Forwarded-For` / `Client-IP` entry is
taken as the peer's address with highest precedence. Any client can therefore register an
arbitrary IP into any swarm.

**Exploit scenario.**
- **Peer-list poisoning / DDoS reflection:** announce with `X-Forwarded-For: <victim-ip>`
  and a chosen port; the tracker hands that IP:port to every other peer in the swarm, and
  their BitTorrent clients will connect to the victim. A tracker becomes a traffic
  amplifier aimed at a third party.
- **Rate-limit evasion:** `announce_check_rate_limit` keys on IP, which the attacker now
  controls.
- **Geo-stats / analytics forgery.**

`honor_xff` defaults to `false` (safe), but it is *required* behind any reverse proxy, and
the config comment explicitly allows leaving `trusted_proxies` empty. So the code's
fail-**open** default (empty list ⇒ trust everyone) is the dangerous direction.

**Confirmed (live):** two requests both from `127.0.0.1`; Peer A sent
`X-Forwarded-For: 203.0.113.50`. Peer B's compact peer list came back containing exactly
`203.0.113.50:6881`, and the tracker echoed `203.0.113.50` as Peer A's BEP-24 "external ip".

**Remediation.** Fail safe: when `honor_xff` is on but `trusted_proxies` is empty, either do
not honor the header, or require `trusted_proxies` to be non-empty to honor it (and log a
startup warning). The APACHE.md / NGINX.md "preferred" path (`honor_xff=false` + the web
server's own `RemoteIP`/`real_ip` from a pinned proxy CIDR) is already the right advice and
should be the strongly-recommended default.

---

### 4. [Medium] Uncaught DB exception → HTTP 500 on announce when `left` omitted/negative
**Where:** `src/model/peer.insert.php:32`, `src/functions/peer.parse.announce.optional.php:21-23`,
`sql/peers.sql:25`
**Class:** Improper handling of exceptional conditions / availability (CWE-703)
**Status:** ✅ FIXED (`647cf39`, `3c1776a`) — `peer_insert`/`peer_update` catch the strict-mode exception; the `left` column is signed (migration) so the `-1` sentinel round-trips; the sibling `port` field is now range-validated. Regression tests added.

`peer_parse_announce_optional()` uses `left = -1` as its "missing/unknown" sentinel when an
announce omits the `left` parameter. The `left` column is `bigint(20) unsigned NOT NULL`,
which cannot store `-1`. Under a strict-mode MySQL/MariaDB (the modern default —
`STRICT_TRANS_TABLES`) and PHP's default `mysqli_report` (ERROR|STRICT, which the app does
not change), `peer_insert`'s `mysqli_execute_query` throws `mysqli_sql_exception`, and —
unlike the sibling write models (`torrent_add`, `torrent_delete`, `torrent_update`,
`torrent_set_listed`, which all wrap the call in try/catch) — `peer_insert` does **not**
catch it. The result is an uncaught fatal → **HTTP 500** on the core announce endpoint.

**Exploit scenario.** An unauthenticated client sends an announce that omits `left` (or
sends `left=-1`, or any negative value). Instead of a graceful bencoded failure, the
endpoint 500s, the peer is never registered, and a stack trace is written to the error log
(and would be shown to the client if `debug`/`display_errors` were ever on). Trivially
scriptable to flood error logs. On a non-strict DB the row instead stores `left=0`
silently, mislabeling a leecher — a data-integrity variant of the same bug.

**Confirmed (live):**
```
announce …&left=0    → HTTP 200 (bencoded)
announce (no left)   → HTTP 500   ("Out of range value for column 'left'")
announce …&left=-1   → HTTP 500
```
Real BitTorrent clients almost always send `left`, so legitimate traffic mostly avoids this
— but it is a reliability defect on the primary endpoint with a trivial anonymous trigger,
and the intended "unknown peer" path is effectively broken against the current schema.

**Remediation.** Catch the strict-mode exception in `peer_insert` and call
`tracker_error()` (mirroring `torrent_add`), **and/or** make the sentinel schema-compatible
— store `0` for unknown and rely on the separate `state` column, or make `left` a signed /
nullable column. Fixing `peer_insert`'s handling also hardens the hot path against any
future strict-mode surprise.

---

### 5. [Low] API keys and TOTP secret stored in plaintext at rest
**Where:** `src/functions/api.authenticate.key.php:18-19`, `config/phoenix.custom.php` (`api_keys`, `admin_totp_secret`)
**Class:** Cleartext storage of sensitive information (CWE-312)
**Status:** ◐ PARTIAL — **API keys are now hashed at rest** (SHA-256; `api_authenticate_key()` hashes the presented key and compares with `hash_equals`), created on the admin API Keys page which stores only the hash and shows each key once. The TOTP secret and `db_pass` inherently must stay reversible (they are reproduced to verify codes / to connect), so they remain plaintext — mitigate with `chmod 600` on the config and keeping it out of the docroot.

Unlike the admin password (bcrypt), API keys and the TOTP secret are stored verbatim in
`phoenix.custom.php` and compared raw (`hash_equals($stored, $input)`). Any exposure of the
config file — a world-readable backup, an LFI, a misconfigured docroot, a shared-hosting
neighbor — hands over full API access and lets an attacker clone the 2FA seed.

The comparison itself is timing-safe and the file lives outside the docroot under the
documented deployments, which is why this is Low, not higher.

**Remediation.** Store a hash of each API key and compare `hash_equals(stored_hash,
hash('sha256', input))`; keys are high-entropy so a fast hash is acceptable. TOTP secrets
must remain reversible, so at least document their at-rest sensitivity and recommend
filesystem permissions (`chmod 600`).

---

### 6. [Low] Open redirect via `REQUEST_URI` in admin login redirect
**Where:** `src/controller/admin.login.php:48`
**Class:** Open redirect (CWE-601) — Semgrep `redirect-to-request-uri`
**Status:** ✅ FIXED (`2671e33`) — `auth_safe_redirect_path()` permits only same-origin absolute paths (rejects `//host`, `/\host`, absolute URLs) with an `admin.php` fallback; applied to the login and logout redirects. Tests added.

`header('Location: '.$_SERVER['REQUEST_URI'])` after a **successful** login will follow a
protocol-relative target: a request path of `//evil.com` yields `Location: //evil.com`,
redirecting off-site. CRLF/header injection is not possible (PHP `header()` blocks it), and
the redirect fires only after valid authentication, so impact is limited to redirecting an
admin who has been lured to a crafted same-host URL and then logs in.

**Remediation.** Redirect to a fixed path (`admin.php`) or validate the target is a
same-origin absolute path (starts with a single `/`). The logout path already trims with
`strtok(…, '?')` but shares the `//host` issue.

---

### 7. [Low] Missing HTTP security headers
**Where:** all endpoints; most important on `public/admin.php`
**Class:** Security misconfiguration (CWE-1021, CWE-693)
**Status:** ✅ FIXED (`aadcca0`) — `http_security_headers()` emits `nosniff` universally + per-group `X-Frame-Options` / `Referrer-Policy` / `Cache-Control: no-store` / CSP; independently reviewed. Strong-CSP (nonces + self-hosting the CDN assets + SRI) tracked as a follow-up.

Responses carry no `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options`,
or `Referrer-Policy`. The admin panel is therefore framable (clickjacking) and its responses
are MIME-sniffable. (`Access-Control-Allow-Origin: *` on the public read endpoints is
intentional and correct; the authenticated API correctly omits it.)

**Remediation.** On `admin.php` responses add `X-Frame-Options: DENY` (or CSP
`frame-ancestors 'none'`), `X-Content-Type-Options: nosniff`, `Referrer-Policy:
same-origin`, and a restrictive `Content-Security-Policy`. These can be set in the app or in
the Apache/Nginx config.

---

### 8. [Low] Empty `admin_password` disables admin authentication entirely
**Where:** `src/controller/admin.login.php:12-15`
**Class:** Missing authentication (CWE-306) — documented operator choice
**Status:** ✅ FIXED — `admin.php` now forces a one-time "set admin password" gate (shared `auth_password_valid` policy: ≥12 chars, ≤72 bytes; optional TOTP enrolment) when `admin_password` is empty, unless the new `admin_auth_optional` setting is deliberately enabled to run unauthenticated. Tested + live-verified.

With `admin_password` empty, `admin_login_controller` returns `null` and the whole panel is
served unauthenticated. The installer now *requires* a password (good), so a fresh install
won't hit this — but a hand-edited or partially-migrated config that leaves it empty exposes
the full panel (torrent CRUD, DB reset/backup). Already documented in the default config and
deployment guides.

**Remediation.** Consider a loud runtime warning banner (or refusing sensitive actions) when
`admin.php` loads with no password set, so the "no auth" state is never silent.

---

## Informational / hardening notes (not vulnerabilities)

- **`full_scrape = true` (default)** returns *every* torrent's stats for an empty or invalid
  scrape. Conventional for open trackers, but on a closed/private tracker it exposes the
  whole torrent list — the config comment already warns about this. Observed during testing
  (an invalid scrape hash fell through to a full scrape).
- **`external_ip = true`** (default off) lets a client set its own address via `?ip=`,
  which is the same arbitrary-IP-injection / reflection class as finding #3 by a second
  path. Keep it off unless required.
- **`is_uploaded_file()` deliberately skipped** in the torrent upload controllers
  (`api.torrent.add.php:44`, `admin.torrent.add.php`). Documented (needed for the test
  harness) and **not exploitable on a real SAPI** — `$_FILES['tmp_name']` is generated by
  PHP core and cannot be pointed at an arbitrary file by the client. Defense-in-depth only;
  restoring the check on non-test SAPIs would close the theoretical gap.
- **No `password_needs_rehash()`** on the admin password — existing hashes won't auto-upgrade
  if `PASSWORD_DEFAULT` changes in a future PHP. Minor.
- **Stale comments** in several files call `maybe_binary_to_hex()` "the project's
  SQL-injection defense." The real defense today is parameterization; the sanitizer is input
  *validation*. Worth correcting so a future change doesn't "trust the sanitizer" and revert
  a model to string concatenation for a non-hex field.

---

## What the audit found done well (verified)

- **No SQL injection.** The `src/model/` layer is fully parameterized
  (`mysqli_execute_query` with bound params, 16 files). Verified three ways: manual review
  of every query site, Semgrep taint rules (zero SQLi findings), and live injection probes
  (`' OR 1=1--`, `';DROP TABLE…`) — all rejected at the `maybe_binary_to_hex` boundary, all
  tables intact. The one request-derived value reaching a raw query (admin Peers `offset`)
  is `(int)`-cast and clamped.
- **No XSS.** Output is escaped at render time. Live-confirmed: a stored torrent name of
  `<script>alert(document.domain)</script>` renders in the public index HTML as
  `&lt;script&gt;…&lt;/script&gt;` and is returned safely as a JSON string.
- **CSRF** — per-session 256-bit token (`random_bytes(32)`), `hash_equals` verification,
  enforced on every state-changing admin form and every API mutation; reads correctly
  exempt.
- **Authentication** — bcrypt admin password; timing-safe (`hash_equals`) API key check;
  fail-closed TOTP 2FA; session cookie hardened (`httponly`, `SameSite=Lax`, conditional
  `secure`) and `session_regenerate_id(true)` on login (anti-fixation). API auth enforcement
  live-confirmed (no key → "Authorization required"; wrong key → "API key is invalid").
- **Authorization** — API torrents are owner-scoped; failed authz returns "Torrent not
  found" so ownership never leaks existence.
- **Bencode decoder** — recursion depth capped at 64 (`BENCODE_DECODE_MAX_DEPTH`) plus the
  1 MB upload size cap: no parser stack-exhaustion or memory DoS. And bencode parsing is
  only reachable via the authenticated upload path.
- **No supply-chain surface** — zero third-party production dependencies; `composer audit`
  clean.
- **Backups download** — the two Semgrep "tainted filename / SSRF" hits in
  `admin.backups.php` are **false positives**: `db_backup_path()` rejects any name where
  `basename($name) !== $name` and then requires an exact match against the actual backup
  allowlist before `readfile()` — no traversal.
- **Deployment guides** (`APACHE.md`/`NGINX.md`) genuinely cover security: config/ kept
  outside docroot, dotfiles denied, `admin.php` flagged as highest-risk with IP-restriction
  and post-setup removal, and XFF trust documented with "never `0.0.0.0/0`".

---

## Appendix — reproduction

Environment (throwaway, disposable):
```bash
# DB
docker run -d --name phoenix-audit-db -e MARIADB_ROOT_PASSWORD=auditpass \
  -e MARIADB_DATABASE=phoenix -p 3306:3306 mariadb:11
# config/phoenix.custom.php → db_host=127.0.0.1, db_user=root, db_pass=auditpass,
#   db_name=phoenix, db_prefix=phoenix_, open_tracker=true  (gitignored)
# schema: include src/phoenix.php then db_create($connection,$settings)  (mirrors tests/bootstrap.php)
# server
php -S 127.0.0.1:8123 -t public
```
Static analysis:
```bash
python3 -m venv ~/audit-venv && ~/audit-venv/bin/pip install semgrep
~/audit-venv/bin/semgrep scan --config p/php --config p/owasp-top-ten \
  --config p/security-audit src public         # 117 rules, 244 files, 3 findings
```
Finding #4 one-liner:
```bash
curl -s -o /dev/null -w '%{http_code}\n' \
  'http://127.0.0.1:8123/announce.php?info_hash=abcabcabcabcabcabcabcabcabcabcabcabcabca&peer_id=dddddddddddddddddddddddddddddddddddddddd&port=6881'
# → 500  (omit &left=0 → crash; add &left=0 → 200)
```
