<?php

declare(strict_types=1);

////	install_build_config
// Renders sanitised install values as a phoenix.custom.php source string.
// Strings are single-quoted with addslashes; booleans become literal
// `true`/`false`. db_reset is always emitted as `false` so a freshly-installed
// tracker never re-runs setup unattended.
function install_build_config(array $values): string {
	$s = function(string $v): string { return '\''.addslashes($v).'\''; };
	$b = function(bool $v): string { return $v ? 'true' : 'false'; };

	$config  = '<?php'.PHP_EOL.PHP_EOL;
	$config .= '$settings[\'db_host\']      = '.$s($values['db_host']).';'.PHP_EOL;
	$config .= '$settings[\'db_user\']      = '.$s($values['db_user']).';'.PHP_EOL;
	$config .= '$settings[\'db_pass\']      = '.$s($values['db_pass']).';'.PHP_EOL;
	$config .= '$settings[\'db_name\']      = '.$s($values['db_name']).';'.PHP_EOL;
	$config .= '$settings[\'db_prefix\']    = '.$s($values['db_prefix']).';'.PHP_EOL;
	$config .= '$settings[\'db_persist\']   = '.$b($values['db_persist']).';'.PHP_EOL;
	$config .= '$settings[\'db_reset\']     = false;'.PHP_EOL;
	$config .= '$settings[\'open_tracker\']    = '.$b($values['open_tracker']).';'.PHP_EOL;
	$config .= '$settings[\'public_index\']    = '.$b($values['public_index']).';'.PHP_EOL;
	$config .= '$settings[\'admin_password\']  = '.$s($values['admin_password']).';'.PHP_EOL;
	return $config;
}
