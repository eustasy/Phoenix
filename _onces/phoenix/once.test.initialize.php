<?php

require_once __DIR__.'/../../_settings/phoenix.default.php';
$test_db = mysqli_connect('127.0.0.1', 'root', '', 'phoenix');
if ( !$test_db ) {
	echo 'Failed to connect to database for testing.';
	$failure = true;
}
$queries[] = 'GRANT ALL ON `phoenix`.* TO '.$settings['db_user'].'@localhost IDENTIFIED BY \''.$settings['db_pass'].'\';';
$queries[] = 'FLUSH PRIVILEGES;';
// TODO Deduplicate
$queries[] = 'CREATE TABLE IF NOT EXISTS `phoenix`.`'.$settings['db_prefix'].'peers` (' .
			'`info_hash` varchar(40) NOT NULL,' .
			'`peer_id` varchar(40) NOT NULL,' .
			'`compactv4` varchar(12) NOT NULL,' .
			'`compactv6` varchar(36) NOT NULL,' .
			'`ipv4` char(15) NOT NULL DEFAULT \'0\',' .
			'`ipv6` char(39) NOT NULL DEFAULT \'0\',' .
			'`portv4` smallint(5) unsigned NOT NULL,' .
			'`portv6` smallint(5) unsigned NOT NULL,' .
			'`left` int(100) unsigned NOT NULL DEFAULT \'0\',' .
			'`state` tinyint(1) unsigned NOT NULL DEFAULT \'0\',' .
			'`updated` int(10) unsigned NOT NULL,' .
			'PRIMARY KEY (`info_hash`,`peer_id`)' .
		') ENGINE=MyISAM DEFAULT CHARSET=latin1;';
$queries[] = 'CREATE TABLE IF NOT EXISTS `phoenix`.`'.$settings['db_prefix'].'tasks` (' .
			'`name` varchar(16) NOT NULL,' .
			'`value` int(10) NOT NULL,' .
			'PRIMARY KEY (`name`)' .
		') ENGINE=MyISAM DEFAULT CHARSET=latin1;';
$queries[] = 'CREATE TABLE IF NOT EXISTS `phoenix`.`'.$settings['db_prefix'].'torrents` (' .
			'`name` varchar(255) NULL,' .
			'`info_hash` varchar(40) NOT NULL,' .
			'`downloads` int(10) unsigned NOT NULL DEFAULT \'0\',' .
			'PRIMARY KEY (`info_hash`)' .
		') ENGINE=MyISAM DEFAULT CHARSET=latin1;';
foreach ( $queries as $query ) {
	$result = mysqli_query($test_db, $query);
	echo 'Test initialized: ';
	var_dump($result);
	if ( !$result ) {
		echo 'Error #'.mysqli_errno($test_db).': "'.mysqli_error($test_db).'"';
		$failure = true;
	}
}
if ( !empty($failure) ) {
	exit(1);
}
