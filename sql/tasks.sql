-- Phoenix tasks table.
--
-- One row per task: the last time it ran (`value`, Unix time) and who ran it
-- (`source`: admin | cron | auto). REPLACE-d on every run, never pruned, so the
-- dashboard always has each task's most recent run. The full run history lives
-- in the phoenix_task_runs append log.
--
-- Uses the literal default prefix `phoenix_`. db_create() rewrites it to
-- $settings['db_prefix'] before executing. To import manually:
--   mysql <database> < sql/tasks.sql
-- and edit the table name first if your install uses a different prefix.

CREATE TABLE IF NOT EXISTS `phoenix_tasks` (
	`name` varchar(16) NOT NULL,
	`value` int(10) NOT NULL,
	`source` varchar(8) NOT NULL DEFAULT '',
	PRIMARY KEY (`name`)
) ENGINE = MyISAM DEFAULT CHARSET = latin1;
