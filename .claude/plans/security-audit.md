# Security Audit — Phoenix BitTorrent Tracker

## Context

The user (a developer, new to security auditing) wants a security audit of Phoenix,
a procedural-PHP BitTorrent tracker (mysqli, no framework). After recon, they chose:

- **Depth:** Static analysis + tooling **and** dynamic testing against a live instance.
- **Deliverable:** A severity-ranked **findings report only** — no code changes, no
  auto-commit, no CI/test wiring. The user decides what to remediate afterward.

Recon (3 exploration passes) already established the baseline:

- **SQLi:** `src/model/` uses **parameterized** queries (`mysqli_execute_query` with bound
  params) throughout. No request-derived value appears to reach an interpolated query.
  `maybe_binary_to_hex()` is now really input *validation*, not the SQLi barrier the docs
  claim (stale comments). **This must be verified rigorously, not trusted.**
- **XSS:** output escaped at render time (`htmlspecialchars` / `xml_escape` / `json_encode`);
  no obvious unescaped reflection.
- **Production dependencies:** none (zero third-party PHP libs at runtime) — the attack
  surface is entirely hand-written code.
- **Higher-interest areas** are logic/config-dependent (see Priority Targets).

The audit's value therefore concentrates in: verifying the SQLi/XSS posture with tooling
+ live probes, the logic/config vulnerabilities, and DoS surface.

## Scope & assets

**In scope:** all of `public/` (announce, scrape, index, magnet, admin, api/*), `src/`
(controllers, models, views, functions, hooks), `config/`, `sql/`, and deployment guidance
in `APACHE.md`/`NGINX.md`.

**Assets to protect:** the MySQL database (integrity + confidentiality), the config file
`phoenix.custom.php` (holds db_pass, plaintext api_keys, TOTP secret, admin bcrypt hash),
the admin session, and the server's code-execution/file-write ability.

**Out of scope (deliverable):** applying fixes, adding tests, wiring CI. (Tooling *is*
installed/run for analysis, but not committed to the repo.)

## Methodology (phases)

### A. Environment & tooling setup (analysis-only, nothing committed)
- Install **Semgrep** as a standalone binary (e.g. `pipx install semgrep`) — no repo impact.
- Install **Psalm** *globally* (`composer global require vimeo/psalm`) or as a scratchpad
  phar, with a `psalm.xml` kept in scratchpad — **do not** modify the repo's
  `composer.json`/`composer.lock`.
- Stand up a **throwaway MariaDB/MySQL** in WSL (install if absent) and a disposable
  `phoenix.custom.php` for the running instance. This is a test rig, not production data;
  the config is removed after. Run the tracker via `php -S` exactly as the smoke suite does.
- Sanity-check the running instance with a benign announce/scrape before attacking it.

### B. Automated static analysis
- **Semgrep** (primary — pattern-based, tolerant of the non-autoloaded procedural layout):
  run `p/php`, `p/owasp-top-ten`, and a security-audit ruleset across `src/` + `public/`.
- **Psalm `--taint-analysis`** (secondary / best-effort — dataflow from `$_GET/$_POST/...`
  sinks to SQL/HTML/file/exec). Expect noise given one-function-per-file with no autoload;
  triage rather than trust wholesale.
- Triage every hit: confirm true positive, dismiss false positive with a reason.

### C. Manual vulnerability-class review
Walk the code **one class at a time** (more reliable than file-by-file), mapping each to
the concrete Phoenix code paths surfaced in recon:
- **SQL injection** — re-verify every `mysqli_query`/`mysqli_execute_query` call site in
  `src/model/`; confirm no superglobal reaches an interpolated fragment; check the
  identifier-interpolation spots (`db_prefix`/`db_name` allowlist in
  `install.sanitize.post.php`, `task.log.php`, `db.drop.php`, `LIMIT` casts).
- **XSS** — confirm output escaping in `src/views/html.*` and `xml_escape`; scrutinize the
  inline-JS spots (`html.admin.geography.php`, `public/magnet.php`).
- **AuthN/AuthZ** — `admin.login.php` (empty-`admin_password` auth-skip), 2FA fail-closed
  path, API `hash_equals` key check, `api.torrent.authorize.php` owner scoping, session
  hardening + `session_regenerate_id`.
- **CSRF** — `auth.csrf.*`, coverage on all state-changing admin/API endpoints.
- **File inclusion / path traversal** — `db.backup.path.php` (`$_GET['download']`),
  `phoenix.hook.php` dynamic include, `config.write.php`/`install_build_config` (`var_export`
  breakout), `$_FILES['torrent']` handling incl. the deliberate `is_uploaded_file()` skip.
- **Header injection / open redirect** — `$_SERVER['REQUEST_URI']` in `Location:` headers
  (`admin.login.php`, `auth.handle.logout.php`).
- **IP-trust / spoofing** — `peer.address.candidates.php` + `peer.proxy.trusted.php` when
  `honor_xff` on and `trusted_proxies` empty (feeds peer lists, geo stats, rate limiting).
- **DoS** — bencode parser (`torrent.parse.*`, deep/huge structures), announce hot path,
  upload size caps, unbounded queries.
- **Secrets & info disclosure** — plaintext `api_keys`/TOTP in config, `debug`/
  `display_errors`, error messages, `password_needs_rehash()` absence.

### D. Dynamic testing (live adversarial probes)
Against the local `php -S` instance, send crafted requests and observe behavior:
- Malformed/oversized `info_hash`/`peer_id`; SQLi metacharacters through announce/scrape.
- Forged `X-Forwarded-For` / `X-Client-IP` to test IP spoofing into peer/geo/rate-limit.
- Header-injection / open-redirect attempts on admin login/logout `Location:`.
- Auth-bypass attempts on `api/*` (missing/forged bearer, method confusion, CSRF omission
  on mutations); installer-mode probing when unconfigured.
- Oversized / malicious `.torrent` uploads and deeply-nested bencode (DoS).
- XSS payloads stored via admin/API, then rendered, to confirm output escaping.
Each probe is designed to confirm or refute a specific suspected finding.

### E. Dependency & configuration review
- Re-run OSV/trivy-style CVE scan (dev tree only, since prod has no libs) via existing qlty
  security plugins for completeness.
- Review default `config/phoenix.default.php` for insecure defaults and the
  `APACHE.md`/`NGINX.md` hardening guidance (docroot exposure of `phoenix.custom.php`,
  proxy trust, TLS).

### F. Reporting
Write up findings, each with: title, **severity** (Critical/High/Medium/Low with rationale),
affected file:line, a concrete **exploit scenario**, evidence (PoC request or code trace),
and **remediation** guidance. Order worst-first. Include a short "what's *good*" section
(parameterization, escaping, CSRF, fail-closed 2FA) so the report is balanced, plus a
"stale-comment / defense-in-depth" note about the `maybe_binary_to_hex` documentation drift.

## Priority targets (dig here first)

1. Unauthenticated **installer mode** in `admin.php` when unconfigured — accepts `$_POST`,
   `mysqli_connect()`s to attacker-supplied creds, and `file_put_contents` a PHP config file.
2. **Auth-skip** when `$settings['admin_password']` is empty (`admin.login.php:12`).
3. **XFF/client-IP spoofing** with empty `trusted_proxies` (`peer.address.candidates.php`).
4. **Plaintext API keys** at rest (`api.authenticate.key.php`, `config.write.php`).
5. **`Location:` header** reflection of `REQUEST_URI` (admin login/logout).
6. **Bencode/upload DoS** (`torrent.parse.*`, `$_FILES['torrent']`).
7. Rigorous re-verification of the **SQLi-safe** claim across `src/model/`.

## Deliverable

A written **security findings report** (markdown), saved to the session scratchpad and
summarized inline. Can additionally be rendered as a shareable Artifact on request. The
repository itself is left unchanged (report-only scope). No commits.

## Verification / how to reproduce

- Every "confirmed" finding includes a reproducible PoC: either a `curl` command against the
  local instance or a precise code trace (file:line → sink).
- The user can reproduce dynamic findings by starting the same `php -S` + MariaDB rig
  (steps included in the report appendix) and replaying the PoC requests.
- Static findings are reproducible by re-running the exact Semgrep/Psalm invocations
  documented in the report.

## Boundaries

- No changes to project source, `composer.json`/`.lock`, tests, or CI.
- Tooling installed globally / in scratchpad only.
- Dynamic testing uses a disposable local DB + config, torn down afterward; no real data.
- No auto-commit (repo convention).
