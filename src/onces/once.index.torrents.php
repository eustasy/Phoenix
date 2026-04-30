<?php

// Only includes torrents with listed=1; unlisted torrents are invisible to the public index.
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
	require_once $settings['functions'].'function.index.render.xml.php';
	header('Content-Type: text/xml');
	echo index_render_xml($index);

// JSON
} else if ( isset($_GET['json']) ) {
	require_once $settings['functions'].'function.index.render.json.php';
	header('Content-Type: application/json');
	echo index_render_json($index);

// HTML
} else {
	require_once $settings['functions'].'function.index.render.html.php';
	header('Content-Type: text/html; charset=UTF-8');
	echo index_render_html($index);
}
