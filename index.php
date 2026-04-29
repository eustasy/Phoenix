<?php

require_once __DIR__.'/_phoenix.php';

if ( !$settings['public_index'] ) {
	tracker_error('Index is not public.');
}

require_once $settings['onces'].'once.index.torrents.php';
