-- Phoenix torrents table.
--
-- Uses the literal default prefix `phoenix_`. db_create() rewrites it to
-- $settings['db_prefix'] before executing. To import manually:
--   mysql <database> < sql/torrents.sql
-- and edit the table name first if your install uses a different prefix.

CREATE TABLE IF NOT EXISTS `phoenix_torrents` (
	`name` varchar(255) NULL,
	`info_hash` varchar(40) NOT NULL,
	`size` bigint(20) unsigned NULL,
	`listed` tinyint(1) unsigned NOT NULL DEFAULT '0',
	`downloads` int(10) unsigned NOT NULL DEFAULT '0',
	PRIMARY KEY (`info_hash`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
