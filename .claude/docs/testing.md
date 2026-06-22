# Testing & CI

## PHPUnit suite (`tests/phoenix/`)

Wired via `composer.json` + `phpunit.xml.dist`. The bootstrap
(`tests/bootstrap.php`):

1. Loads Composer's autoloader and `src/phoenix.php` (so `$connection`,
   `$settings`, `$time` are available).
2. **Suffixes `$settings['db_prefix']` with `TESTING_`** so tests can't touch
   production tables.
3. Calls `db_create()` to ensure the prefixed tables exist.
4. Exposes the globals via `$GLOBALS`.

All test classes are in the `Phoenix\Tests` namespace and extend
`PhoenixTestCase` (`tests/phoenix/PhoenixTestCase.php`), which copies the globals
into `protected static` properties (`self::$connection`, `self::$settings`,
`self::$time`).

Conventions:

- **One test class per function/component**, PSR-4 named (`Phoenix\Tests\` →
  `tests/phoenix/`): `ParseIpv4Test.php` → `class ParseIpv4Test`. PHPUnit
  discovers classes ending in `Test`.
- Tests that mutate the DB use **`__TEST_%`-prefixed fixture values** and clean
  up in `tearDown()`. (`task_clean()` removes such rows, so `TaskCleanTest`
  relies on that rather than fixture cleanup.)
- To test functions that `exit()` (notably `tracker_error()`), **spawn a
  subprocess** via `proc_open(PHP_BINARY, ...)` and assert on captured stdout +
  exit code — see `TrackerErrorTest.php`. Running in-process would kill the
  PHPUnit worker.

Requires a reachable DB and a `phoenix.custom.php`. When adding a function,
mirror the structure of the test for a sibling function.

Run:

```bash
vendor/bin/phpunit                                  # full suite
vendor/bin/phpunit --filter ParseIpv4Test           # one class
vendor/bin/phpunit --filter 'ParseIpv4Test::testIpv4WithPort'  # one method
```

## Smoke suite (`tests/smoke/`)

A separate suite (`phpunit.smoke.xml.dist`, bootstrap `tests/smoke/bootstrap.php`)
that does **not** touch the DB or load `phoenix.php` — it only speaks HTTP to a
running `php -S` instance (`SMOKE_BASE_URL`). It just needs the autoloader. Used
by the Docker dev environment and `smoke-php.yml` CI. See `CONTRIBUTING.md` for
the Docker setup.

## Lint / static analysis (qlty)

`qlty check` (changed files) / `qlty check --all` (whole tree) runs every
configured linter and formatter: phpstan (level 9) + php-cs-fixer (PHP),
sqlfluff (SQL), markdownlint, stylelint, oxc, prettier, shellcheck/shfmt,
yamllint/actionlint/zizmor, plus security scanners (gitleaks, trivy,
osv-scanner). Config in `.qlty/qlty.toml` and `.qlty/configs/`.

Note (`.qlty/qlty.toml`): qlty's built-in sqlfluff driver hardcodes
`--dialect ansi`, so the driver `script` is re-declared there to drop the flag
and let `.qlty/configs/.sqlfluff`'s `dialect` win. phpstan and most linters skip
`tests/`, `.qlty/`, `.vscode/`, `.claude/`; security scanners still see everything.

## CI workflows (`.github/workflows/`)

`php.yml` (phpstan + php-cs-fixer via qlty), `test-php.yml` (PHPUnit against a
MariaDB service container, PHP 8.2–8.6), `smoke-php.yml` (HTTP smoke against
`php -S`), `sql.yml` (sqlfluff), `md.yml`, `css.yml`, `html.yml`, `js.yml`,
`sh.yml`, `yaml.yml`, and `security.yml`.
