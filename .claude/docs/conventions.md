# Conventions

From `CONTRIBUTING.md` and consistent practice. CI enforces most of these:
`ConventionsTest` checks the structural rules, qlty runs php-cs-fixer + phpstan.

## One function per file

Every file in `src/functions/`, `src/model/`, `src/views/`, and
`src/controller/` defines **exactly one function** — helpers get their own file,
never a second function below the main one. `ConventionsTest` fails the suite
otherwise.

- File name mirrors the function name, `_` → `.`: `parse_ipv4()` →
  `parse.ipv4.php`; `stats_client_version()` → `stats.client.version.php`.
- Naming is `<category>.<verb>.php` (`peer.resolve.addresses.php`,
  `torrent.add.php`, `db.connect.php`).
- A function `require_once`s the helpers it calls at the top of its own body, so
  every file declares its own dependencies. Only carve-out: `tracker_error()`
  (bootstrap-loaded — never `require_once` it).
- `src/hooks/` files are **scripts, not function definitions** — they execute
  inside `phoenix_hook()`'s scope via plain `include` and must not declare
  functions at top level (a redeclaration would fatal on the second event in a
  long-lived FPM worker). See [stats-hooks.md](stats-hooks.md).

## File layout

- `declare(strict_types=1);` first in every PHP file.
- **Four-stroke section header**: `////` then a tab, then the function name;
  a short `//` description line; then the function. The same header style marks
  logical sections inside a function body.
- **No closing `?>`** on PHP-only files.
- Tabs for indentation, spaces for alignment. (php-cs-fixer enforces PSR-12,
  i.e. it expects this as four-wide; SQL files use tabs.)

## Style & static analysis

- PHP is PSR-12, enforced by php-cs-fixer via qlty
  (`.qlty/configs/.php-cs-fixer.dist.php`).
- phpstan runs at **level 9** (`.qlty/configs/phpstan.dist.neon`); project-wide
  array shapes (`PhoenixSettings`, `PhoenixPeer`) are type aliases declared
  there — use them in docblocks (`@param PhoenixSettings $settings`). There is a
  deferred backlog of `missingType` findings; don't add new ones.
- **Settings over hardcoded behavior**: any tunable (size, count, on/off, path)
  gets a key in `config/phoenix.default.php` with a sensible default and a
  one-line `/* comment */`. See [configuration.md](configuration.md).
- **PHP-native over shell** for maintenance/utility code, so config stays in
  `$settings` (e.g. `bin/backup-database.php`, not a `.sh`).

## Cast DB columns

mysqli returns numeric columns as strings. Cast to `int` before encoding —
otherwise bencode/JSON emit them as strings, not integers.

## Commits

- `Fix #<issue>: <Title from issue>.` **verbatim** from the GitHub issue when the
  work closes a tracked issue — run `gh issue view <N>` first to confirm it's
  still open and copy the title.
- Otherwise a short descriptive subject, present tense.
- One concern per commit; avoid batching unrelated fixes.
- Include the `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>` trailer
  (or current model).
- **The user commits as they go — never auto-commit or offer to.**
