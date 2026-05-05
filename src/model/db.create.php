<?php

declare(strict_types=1);

////	db_create
// Creates the peers, tasks, and torrents tables under db_name with the configured prefix.
// MyISAM is chosen over InnoDB: the tracker is write-heavy and never needs transactions or foreign keys.
function db_create(mysqli $connection, array $settings, bool $debug = false): bool {

	$queries = array();
	$queries[] = 'CREATE TABLE IF NOT EXISTS `'.$settings['db_name'].'`.`'.$settings['db_prefix'].'peers` (' .
				'`info_hash` varchar(40) NOT NULL,' .
				'`peer_id` varchar(40) NOT NULL,' .
				'`compactv4` varchar(12) NOT NULL,' .
				'`compactv6` varchar(36) NOT NULL,' .
				// Empty string is the "no address" sentinel and matches what
				// peer_insert writes when only the other family is present.
				// '' is a normal, non-NULL value in MySQL/MariaDB (the
				// '' == NULL conflation is Oracle-specific and does not apply).
				'`ipv4` char(15) NOT NULL DEFAULT \'\',' .
				'`ipv6` char(39) NOT NULL DEFAULT \'\',' .
				'`portv4` smallint(5) unsigned NOT NULL,' .
				'`portv6` smallint(5) unsigned NOT NULL,' .
				'`uploaded` bigint(20) unsigned NOT NULL DEFAULT \'0\',' .
				'`downloaded` bigint(20) unsigned NOT NULL DEFAULT \'0\',' .
				'`left` bigint(20) unsigned NOT NULL DEFAULT \'0\',' .
				'`state` tinyint(1) unsigned NOT NULL DEFAULT \'0\',' .
				'`updated` int(10) unsigned NOT NULL,' .
				'PRIMARY KEY (`info_hash`,`peer_id`)' .
			') ENGINE=MyISAM DEFAULT CHARSET=latin1;';
	$queries[] = 'CREATE TABLE IF NOT EXISTS `'.$settings['db_name'].'`.`'.$settings['db_prefix'].'tasks` (' .
				'`name` varchar(16) NOT NULL,' .
				'`value` int(10) NOT NULL,' .
				'PRIMARY KEY (`name`)' .
			') ENGINE=MyISAM DEFAULT CHARSET=latin1;';
	$queries[] = 'CREATE TABLE IF NOT EXISTS `'.$settings['db_name'].'`.`'.$settings['db_prefix'].'torrents` (' .
				'`name` varchar(255) NULL,' .
				'`info_hash` varchar(40) NOT NULL,' .
				'`size` bigint(20) unsigned NULL,' .
				'`listed` tinyint(1) unsigned NOT NULL DEFAULT \'0\',' .
				'`downloads` int(10) unsigned NOT NULL DEFAULT \'0\',' .
				'PRIMARY KEY (`info_hash`)' .
			') ENGINE=MyISAM DEFAULT CHARSET=latin1;';

	$failure = false;
	foreach ( $queries as $query ) {
		$result = mysqli_query($connection, $query);
		if ( !$result ) {
			if ( $debug ) {
				echo 'Error #'.mysqli_errno($connection).': "'.mysqli_error($connection).'" while running "'.$query.'"'.PHP_EOL;
			}
			$failure = true;
		}
	}

	if ( $failure ) {
		if ( $debug ) {
			echo 'Database Creation failed.'.PHP_EOL;
		}
		return false;
	}
	if ( $debug ) {
		echo 'Database Creation successful.'.PHP_EOL;
	}
	return true;
}
