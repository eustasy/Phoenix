<?php

////	Fatal Error
// Exits with a tracker-format error.
require_once __DIR__.'/../views/bencode.error.php';

function tracker_error(string $error): never {
	echo view_error_bencode($error);
	exit(2);
}
