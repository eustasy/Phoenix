-- Phoenix migration: v3.2 (haggard) schema changes.
--
-- Brings a pre-3.2 install up to the 3.2/4.0 schema by normalising the
-- ipv4/ipv6/left column types and adding the uploaded/downloaded peer
-- columns and the size/listed torrent columns.  MODIFY statements are
-- exact no-ops against the current sql/peers.sql; ADD COLUMN IF NOT EXISTS
-- makes every statement safe to run more than once.
--
-- Uses the literal default prefix `phoenix_`. db_migrate() rewrites it to
-- $settings['db_prefix'] before executing. To import manually:
--   mysql <database> < sql/migrations/2026-05-09-3.2-haggard.sql
-- and edit the table name first if your install uses a different prefix.

ALTER TABLE `phoenix_peers`
MODIFY `ipv4` char(15) NOT NULL DEFAULT '',
MODIFY `ipv6` char(39) NOT NULL DEFAULT '',
MODIFY `left` bigint(20) unsigned NOT NULL DEFAULT '0',
ADD COLUMN IF NOT EXISTS `uploaded` bigint(20) unsigned NOT NULL DEFAULT '0' AFTER `portv6`,
ADD COLUMN IF NOT EXISTS `downloaded` bigint(20) unsigned NOT NULL DEFAULT '0' AFTER `uploaded`;

ALTER TABLE `phoenix_torrents`
ADD COLUMN IF NOT EXISTS `size` bigint(20) unsigned NULL AFTER `info_hash`,
ADD COLUMN IF NOT EXISTS `listed` tinyint(1) unsigned NOT NULL DEFAULT '0' AFTER `size`;
