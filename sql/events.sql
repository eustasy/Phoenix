-- Phoenix events table.
--
-- Uses the literal default prefix `phoenix_`. db_create() rewrites it to
-- $settings['db_prefix'] before executing. To import manually:
--   mysql <database> < sql/events.sql
-- and edit the table name first if your install uses a different prefix.
--
-- Opt-in stat-tracking ledger (see the stats_* settings): one row per logged
-- torrent event. Privacy-preserving by design — `client` is a coarse label
-- derived from (and never storing) the peer_id, `country`/`continent` are
-- minified ISO codes derived from (and never storing) the IP, and `user` is
-- the torrent's owner, not the peer. The table is created at install but
-- stays empty until $settings['stats_enabled'] is turned on.

CREATE TABLE IF NOT EXISTS `phoenix_events` (
	`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	`time` int(10) unsigned NOT NULL,
	`info_hash` varchar(40) NOT NULL,
	`event` varchar(16) NOT NULL,
	`client` varchar(64) NOT NULL DEFAULT '',
	`user` varchar(255) NOT NULL DEFAULT '',
	`country` char(2) NOT NULL DEFAULT '',
	`continent` char(2) NOT NULL DEFAULT '',
	PRIMARY KEY (`id`),
	KEY `time` (`time`),
	KEY `info_hash` (`info_hash`)
) ENGINE = MyISAM DEFAULT CHARSET = latin1;
