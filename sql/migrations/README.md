# Migrations

Intentionally empty as of 5.0.

Phoenix 5.0 is a clean-install release: every schema change that the 3.x and
4.x migrations applied is folded into `sql/*.sql`, so `db_create()` produces the
finished schema in one step and there is nothing left to migrate onto it.

`db_migrate()` still runs — it globs this directory, finds nothing, and reports
success — so the Utilities → Migrate action and the 5.x upgrade path are intact
for whenever the first 5.x schema change lands.

## Adding one

Name it `YYYY-MM-DD_<version>-<slug>.sql` and write it to be **idempotent**.
`db_migrate()` keeps no record of what it has applied: it re-executes every file
in this directory on every call, so each statement must be safe to run
repeatedly — `ADD COLUMN IF NOT EXISTS`, a `MODIFY` that restates the finished
definition, or a guard built from `information_schema` for statements that have
no `IF NOT EXISTS` form (`ALTER TABLE … ENGINE=…` rebuilds a table that already
matches, so it needs one).

`db_migrate()` strips `--` line comments and then splits on `;`, so a semicolon
inside a `--` comment is harmless but one inside any other comment form is not —
the split would cut the comment in half and send the remainder to the server.
The split is also why each file may hold several statements: `mysqli_query()`
runs only the first statement of a multi-statement string.

Use the literal default prefix `phoenix_`; `db_migrate()` rewrites it to the
install's actual prefix before executing.
