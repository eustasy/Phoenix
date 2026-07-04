-- Phoenix migration: make phoenix_peers.`left` signed.
--
-- peer_parse_announce_optional() uses left=-1 as the "bytes remaining unknown"
-- sentinel when a client omits `left` (missing left => left=-1, state=0). The
-- column was `bigint unsigned`, which cannot store -1: under a strict-mode
-- MySQL/MariaDB (the modern default) the announce INSERT/UPDATE threw an
-- out-of-range exception and the endpoint 500'd. Widen it to signed so the
-- sentinel round-trips. Seeder/leecher counts read the `state` column, not
-- `left`, so signedness has no effect on aggregation.
--
-- MODIFY re-applies the same definition on every run (the `left` column always
-- exists), so this file is idempotent and safe to run more than once.
--
-- Uses the literal default prefix `phoenix_`. db_migrate() rewrites it to
-- $settings['db_prefix'] before executing. To import manually:
--   mysql <database> < sql/migrations/2026-07-04_4.3-peers-left-signed.sql
-- and edit the table name first if your install uses a different prefix.

ALTER TABLE `phoenix_peers`
MODIFY `left` bigint(20) NOT NULL DEFAULT '0';
