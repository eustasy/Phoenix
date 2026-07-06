# CLAUDE.md

Guidance for Claude Code working in this repository. This file is an index;
the detail lives in `.claude/docs/`. Read the linked doc before working in
that area — they hold the conventions and gotchas CI enforces but the code
doesn't make obvious.

## Project

**Phoenix** is a lightweight BitTorrent tracker in procedural PHP with a
MySQL/MariaDB backend. It implements announce/scrape (BEP 3, 7, 23, 24, 27,
48). It is a tracker first, not a torrent listing site — but it ships an
optional public index (`public/index.php`), a public stats page
(`public/scrape.php?stats`), a client-side magnet generator
(`public/magnet.php`), an admin panel + first-run installer (`public/admin.php`),
and an authenticated management REST API (`public/api/`).

**Stack:** PHP 8.2–8.6, `mysqli`. No framework, no front-end build step. Optional
Composer libraries (`geoip2/geoip2` for geo stats, `eustasy/authenticatron` for
admin 2FA), loaded conditionally and guarded with `class_exists()`. PHPUnit for
tests; qlty orchestrates phpstan (level 9) + php-cs-fixer + other linters.

## Non-negotiable invariants

These break CI or security if violated. Details in the linked docs.

- **One function per file** in `src/{functions,model,views,controller}/`;
  file name = function name with `.` for `_` (`parse_ipv4()` → `parse.ipv4.php`).
  Enforced by `ConventionsTest`. → [conventions](docs/conventions.md)
- **A function `require_once`s its own dependencies** at the top of its body.
  The sole exception is `tracker_error()` (bootstrap-loaded).
  → [architecture](docs/architecture.md)
- **`info_hash`/`peer_id` pass through `maybe_binary_to_hex()`** at the boundary.
  Queries are parameterized (`mysqli_execute_query` with bound `?` params) —
  that's the actual SQL-injection defense; this sanitizer validates/normalizes
  the value before it's bound. → [database](docs/database.md)
- **Bencode is never hand-assembled** — views build a PHP structure and hand it
  to `bencode_encode()`, the single emitter. → [views](docs/views.md)
- **Every settable value exists in `config/phoenix.default.php`.** Code reads
  `$settings['key']` with no fallback layer. → [configuration](docs/configuration.md)
- `declare(strict_types=1);` first in every PHP file; tabs for indentation; no
  closing `?>`. → [conventions](docs/conventions.md)

## Verifying changes

```bash
composer install                      # PHPUnit + dev deps (once)
vendor/bin/phpunit                    # full suite (needs a reachable DB)
vendor/bin/phpunit --filter ParseIpv4Test          # one class
vendor/bin/phpunit --filter 'ParseIpv4Test::testIpv4WithPort'  # one method
qlty check                            # lint/format/static-analysis (changed files)
qlty check --all                      # whole tree, as CI does
```

Tests need a reachable DB and a `phoenix.custom.php`; the bootstrap suffixes the
table prefix with `TESTING_` so it never touches production tables. Match an
existing test's structure when adding one. → [testing](docs/testing.md)

## Workflow rules

- **Never auto-commit.** The user commits as they go. Don't offer to.
- When work closes a tracked issue, run `gh issue view <N>` and use
  `Fix #<N>: <Title>.` verbatim. Otherwise a short present-tense subject.
  One concern per commit. Include the `Co-Authored-By:` trailer.
- New tunable behavior → add a setting, don't hardcode.
- `config/phoenix.default.php` says "do not modify" — that's for *installs*;
  it is the right place to add new settings during development.

## Documentation map

Each entry lists the source paths it covers — read the doc before touching those
paths.

- [architecture.md](docs/architecture.md) — `src/phoenix.php`, `public/*.php`,
  `src/controller/`, `bin/`. Puff layout, the five layers, entry points,
  bootstrap, and how a request flows through announce/scrape/admin/api.
- [conventions.md](docs/conventions.md) — all of `src/`, `tests/phoenix/ConventionsTest.php`,
  `.qlty/configs/`. File structure, naming, four-stroke headers, style, commits.
  Read before adding any file.
- [database.md](docs/database.md) — `sql/`, `sql/migrations/`, `src/model/`,
  `src/functions/db.*.php`, `sanitize.maybe_binary_to_hex.php`. Tables, schema
  files, prefixing, migrations, the hex-sanitizer SQLi defense, `db_host` `p:`.
- [http-api.md](docs/http-api.md) — `public/admin.php`, `public/api/`,
  `src/controller/{admin,api}.*.php`, `src/functions/{auth,api}.*.php`,
  `torrent.parse.*.php`. Admin panel router, REST API, and the auth model.
- [views.md](docs/views.md) — `src/views/`, `src/functions/bencode.*.php`,
  `src/partials/`. Output layers (bencode/JSON/XML/HTML), the bencode emitter
  contract, content negotiation. HTML redesign brief in [DESIGN.md](DESIGN.md).
- [configuration.md](docs/configuration.md) — `config/phoenix.default.php`,
  `config/phoenix.custom.php`, `src/functions/settings.load.php`. The settings
  model and a guide to the notable `$settings` keys.
- [stats-hooks.md](docs/stats-hooks.md) — `src/hooks/`, `src/functions/{phoenix.hook,stats.*}.php`,
  `src/model/{event,events,stats}.*.php`. Events ledger, lifecycle hooks, geo.
- [testing.md](docs/testing.md) — `tests/`, `phpunit*.xml.dist`, `.qlty/`,
  `.github/workflows/`. PHPUnit setup, smoke suite, CI workflows, qlty.

Repo-level docs: `README.md` (install/config), `CONTRIBUTING.md` (dev env, Docker),
`MIGRATING.md` (3.x→4.0), `APACHE.md`/`NGINX.md` (server config), `BEPs.md` (spec
coverage), `CHANGELOG.md`.
