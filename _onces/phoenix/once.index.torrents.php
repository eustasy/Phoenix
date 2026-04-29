<?php

$sql = 'SELECT
		`t`.`info_hash` AS `info_hash`,
		`t`.`name` AS `name`,
		`t`.`size` AS `size`,
		`t`.`downloads` AS `downloads`,
		IFNULL(SUM(`p`.`state`=\'1\'), 0) AS `seeders`,
		IFNULL(SUM(`p`.`state`=\'0\'), 0) AS `leechers`
	FROM `'.$settings['db_prefix'].'torrents` AS `t`
	LEFT JOIN `'.$settings['db_prefix'].'peers` AS `p` ON `t`.`info_hash` = `p`.`info_hash`
	WHERE `t`.`listed` = 1
	GROUP BY `t`.`info_hash`
	ORDER BY `t`.`name`;';

$result = mysqli_query($connection, $sql);
if ( !$result ) {
	tracker_error('Unable to get index.');
}

$index = array();
while ( $row = mysqli_fetch_assoc($result) ) {
	$index[] = array(
		'info_hash' => $row['info_hash'],
		'name'      => $row['name'],
		'size'      => intval($row['size']),
		'downloads' => intval($row['downloads']),
		'seeders'   => intval($row['seeders']),
		'leechers'  => intval($row['leechers']),
		'peers'     => intval($row['seeders']) + intval($row['leechers']),
		'traffic'   => intval($row['size']) * intval($row['downloads']),
	);
}

// XML
if ( isset($_GET['xml']) ) {
	header('Content-Type: text/xml');
	$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><torrents>';
	foreach ( $index as $torrent ) {
		$xml .= '<torrent>'.
			'<info_hash>'.$torrent['info_hash'].'</info_hash>'.
			'<name>'.htmlspecialchars($torrent['name']).'</name>'.
			'<size>'.$torrent['size'].'</size>'.
			'<downloads>'.$torrent['downloads'].'</downloads>'.
			'<seeders>'.$torrent['seeders'].'</seeders>'.
			'<leechers>'.$torrent['leechers'].'</leechers>'.
			'<peers>'.$torrent['peers'].'</peers>'.
			'<traffic>'.$torrent['traffic'].'</traffic>'.
		'</torrent>';
	}
	echo $xml.'</torrents>';

// JSON
} else if ( isset($_GET['json']) ) {
	header('Content-Type: application/json');
	echo json_encode($index);

// HTML
} else {
	header('Content-Type: text/html; charset=UTF-8');
	echo '<!DocType html><html><head><meta charset="UTF-8"><title>Torrent Index</title></head><body><ul>';
	foreach ( $index as $torrent ) {
		echo '<li>'.htmlspecialchars($torrent['name']).
			' &mdash; '.$torrent['seeders'].' seeders,'.
			' '.$torrent['leechers'].' leechers,'.
			' '.$torrent['downloads'].' downloads</li>';
	}
	echo '</ul></body></html>';
}
