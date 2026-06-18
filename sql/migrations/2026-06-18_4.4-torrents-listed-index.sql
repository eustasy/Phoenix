-- Phoenix migration: index the torrents `listed` flag.
--
-- Speeds the public index, which filters `WHERE listed = 1` on what is
-- otherwise a full table scan of the torrents table.
--
-- NOTE: like the task-history migration this is NOT idempotent — ADD INDEX
-- errors if the index already exists, so run it exactly once. (db_migrate runs
-- every file on each "Upgrade Schema"; a second run reports an error even
-- though the index is already present.)
--
-- Uses the literal default prefix `phoenix_`. db_migrate() rewrites it to
-- $settings['db_prefix'] before executing. To import manually:
--   mysql <database> < sql/migrations/2026-06-18_4.4-torrents-listed-index.sql
-- and edit the table name first if your install uses a different prefix.

ALTER TABLE `phoenix_torrents`
ADD INDEX `listed` (`listed`);
