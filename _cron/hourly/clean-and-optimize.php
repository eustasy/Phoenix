<?php

require_once __DIR__.'/../../_phoenix.php';

if ( $settings['clean_with_cron'] ) {
	require_once $settings['functions'].'function.task.clean.php';
	require_once $settings['functions'].'function.task.optimize.php';
	task_clean($connection, $settings, $time);
	task_optimize($connection, $settings, $time);
}
