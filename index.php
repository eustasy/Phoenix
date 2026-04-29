<?php

// Public torrent index; exits with a tracker error if public_index is disabled in settings.
require_once __DIR__.'/_phoenix.php';

if ( !$settings['public_index'] ) {
	tracker_error('Index is not public.');
}

require_once $settings['onces'].'once.index.torrents.php';
