-- Phoenix tasks table.
--
-- Uses the literal default prefix `phoenix_`. db_create() rewrites it to
-- $settings['db_prefix'] before executing. To import manually:
--   mysql <database> < sql/tasks.sql
-- and edit the table name first if your install uses a different prefix.

CREATE TABLE IF NOT EXISTS `phoenix_tasks` (
	`name` varchar(16) NOT NULL,
	`value` int(10) NOT NULL,
	PRIMARY KEY (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
