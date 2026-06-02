-- Phoenix peers table.
--
-- Uses the literal default prefix `phoenix_`. db_create() rewrites it to
-- $settings['db_prefix'] before executing. To import manually:
--   mysql <database> < sql/peers.sql
-- and edit the table name first if your install uses a different prefix.
--
-- MyISAM is chosen over InnoDB because the tracker is write-heavy and never
-- needs transactions or foreign keys.
--
-- `ipv4` and `ipv6` default to '' as the "no address" sentinel, matching what
-- peer_insert writes when only the other family is present.

CREATE TABLE IF NOT EXISTS `phoenix_peers` (
	`info_hash` varchar(40) NOT NULL,
	`peer_id` varchar(40) NOT NULL,
	`compactv4` varchar(12) NOT NULL,
	`compactv6` varchar(36) NOT NULL,
	`ipv4` char(15) NOT NULL DEFAULT '',
	`ipv6` char(39) NOT NULL DEFAULT '',
	`portv4` smallint(5) unsigned NOT NULL,
	`portv6` smallint(5) unsigned NOT NULL,
	`uploaded` bigint(20) unsigned NOT NULL DEFAULT '0',
	`downloaded` bigint(20) unsigned NOT NULL DEFAULT '0',
	`left` bigint(20) unsigned NOT NULL DEFAULT '0',
	`state` tinyint(1) unsigned NOT NULL DEFAULT '0',
	`updated` int(10) unsigned NOT NULL,
	PRIMARY KEY (`info_hash`, `peer_id`)
) ENGINE = MyISAM DEFAULT CHARSET = latin1;
