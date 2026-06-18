-- Phoenix migration: task source + run history.
--
-- Adds a `source` column to phoenix_tasks (who last ran each task: admin / cron
-- / auto) and creates the phoenix_task_runs append log that records every run.
-- The phoenix_tasks table stays one-row-per-task (last run, never pruned);
-- phoenix_task_runs holds the full history, pruned by task_retention.
--
-- Both statements are idempotent (ADD COLUMN IF NOT EXISTS / CREATE TABLE IF
-- NOT EXISTS), so the file is safe to run more than once.
--
-- Uses the literal default prefix `phoenix_`. db_migrate() rewrites it to
-- $settings['db_prefix'] before executing. To import manually:
--   mysql <database> < sql/migrations/2026-06-18_4.4-task-history.sql
-- and edit the table name first if your install uses a different prefix.

ALTER TABLE `phoenix_tasks`
ADD COLUMN IF NOT EXISTS `source` varchar(8) NOT NULL DEFAULT '' AFTER `value`;

CREATE TABLE IF NOT EXISTS `phoenix_task_runs` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`name` varchar(16) NOT NULL,
	`value` int(10) NOT NULL,
	`source` varchar(8) NOT NULL DEFAULT '',
	PRIMARY KEY (`id`),
	KEY `name` (`name`)
) ENGINE = MyISAM DEFAULT CHARSET = latin1;
