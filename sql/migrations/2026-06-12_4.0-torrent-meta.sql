-- Phoenix migration: add user and meta columns to phoenix_torrents.
--
-- Brings a 4.0beta3/3.2 install up to the current schema by adding the
-- user, filename, files, trackers, and webseeds columns.  Every statement
-- uses ADD COLUMN IF NOT EXISTS so the file is safe to run more than once.
--
-- Uses the literal default prefix `phoenix_`. db_migrate() rewrites it to
-- $settings['db_prefix'] before executing. To import manually:
--   mysql <database> < sql/migrations/2026-06-12_4.0-torrent-meta.sql
-- and edit the table name first if your install uses a different prefix.

ALTER TABLE `phoenix_torrents`
ADD COLUMN IF NOT EXISTS `user` varchar(255) NULL FIRST,
ADD COLUMN IF NOT EXISTS `filename` varchar(255) NULL,
ADD COLUMN IF NOT EXISTS `files` longtext NULL,
ADD COLUMN IF NOT EXISTS `trackers` longtext NULL,
ADD COLUMN IF NOT EXISTS `webseeds` longtext NULL;
