<?php

declare(strict_types=1);

////	db_connect
// Wraps mysqli_connect() in a try/catch so callers always get back either a
// usable connection or false, regardless of the active mysqli_report() mode.
//
// PHP 8.1+ defaults mysqli_report to MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT,
// so a bad host/credentials throws mysqli_sql_exception instead of returning
// false. The @-prefix suppresses the connection warning in MYSQLI_REPORT_OFF
// mode; the catch block handles the strict mode.

function db_connect(array $settings): mysqli|false {
	try {
		return @mysqli_connect(
			$settings['db_host'],
			$settings['db_user'],
			$settings['db_pass'],
			$settings['db_name']
		);
	} catch (mysqli_sql_exception $e) {
		return false;
	}
}
