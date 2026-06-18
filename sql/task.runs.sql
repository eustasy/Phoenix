-- Phoenix task-run history table.
--
-- An append log: one row per maintenance-task run, recording the task name,
-- when it ran (`value`, Unix time), and who ran it (`source`: admin | cron |
-- auto). The phoenix_tasks table holds only the latest run per task (for the
-- dashboard, never pruned); this table keeps the full history, pruned by
-- task_retention.
--
-- Uses the literal default prefix `phoenix_`. db_create() rewrites it to
-- $settings['db_prefix'] before executing. To import manually:
--   mysql <database> < sql/task.runs.sql
-- and edit the table name first if your install uses a different prefix.

CREATE TABLE IF NOT EXISTS `phoenix_task_runs` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`name` varchar(16) NOT NULL,
	`value` int(10) NOT NULL,
	`source` varchar(8) NOT NULL DEFAULT '',
	PRIMARY KEY (`id`),
	KEY `name` (`name`)
) ENGINE = MyISAM DEFAULT CHARSET = latin1;
