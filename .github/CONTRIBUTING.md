# Contributing to Phoenix

Phoenix follows what the changelog calls a "puff-style" layout: small,
single-purpose files glued together by `require_once`. These conventions keep
that layout navigable; CI enforces most of them (qlty runs phpstan and
php-cs-fixer, `ConventionsTest` checks the structural rules).

## One function per file

**Every file in `src/functions/`, `src/model/`, `src/views/`, and
`src/controller/` defines exactly one function.** Helpers get their own file,
not a second function below the main one — `tests/phoenix/ConventionsTest.php`
fails the suite otherwise.

* The file name mirrors the function name, with underscores becoming dots:
  `parse_ipv4()` lives in `parse.ipv4.php`, `stats_client_version()` in
  `stats.client.version.php`.
* A function `require_once`s the helpers it calls at the top of its own body
  (see `torrent_parse()`), so every file declares its own dependencies.
* The one carve-out: `tracker_error()` is loaded by the bootstrap, so
  functions never `require_once` it themselves.
* `src/hooks/` files are scripts, not function definitions — they execute
  inside `phoenix_hook()`'s scope and must **not** declare functions at top
  level (the dispatcher uses plain `include`, so a declaration would fatal on
  the second event in a process).

## File layout

* `declare(strict_types=1);` first in every PHP file.
* A "four stroke" section header naming the function — `////` then a tab,
  then the function name — followed by a short `//` description, then the
  function. The same header style marks logical sections inside functions.
* No closing `?>` on PHP-only files.

## Style

* PHP is PSR-12 — four-space indentation — enforced by php-cs-fixer via
  `qlty check` (config in `.qlty/configs/.php-cs-fixer.dist.php`). SQL files
  use tabs.
* phpstan runs at level 9; project-wide array shapes (`PhoenixSettings`,
  `PhoenixPeer`) are type aliases in `.qlty/configs/phpstan.dist.neon`.
* Settings over hardcoded behavior: any tunable gets a key in
  `config/phoenix.default.php` with a sensible default and a one-line
  comment. Code reads `$settings['key']` directly, so every key must exist
  in the default file.
* PHP-native solutions over shell scripts for maintenance/utility code, so
  configuration stays in `$settings` (e.g. `bin/backup-database.php`, not a
  `.sh`).

## Tests

* One PHPUnit test class per function/component in `tests/phoenix/`, PSR-4
  named (`ParseIpv4Test.php` → `class ParseIpv4Test`), extending
  `PhoenixTestCase`.
* Tests that mutate the database use `__TEST_%`-prefixed fixture values and
  clean up in `tearDown()`.
* Functions that `exit()` (like `tracker_error()`) are tested in a
  subprocess — see `TrackerErrorTest`.

## Commits

* `Fix #<issue>: <Title from issue>.` verbatim when the work closes a
  tracked issue; otherwise a short descriptive subject in present tense.
* One concern per commit.
