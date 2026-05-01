<?php

declare(strict_types=1);

////	Fatal Error
// Exits with a tracker-format error.

require_once $settings['views'].'bencode.error.php';

function tracker_error(string $error): never {
	echo view_error_bencode($error);
	exit(2);
}
