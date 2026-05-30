<?php

declare(strict_types=1);

////	Fatal Error
// Exits with a tracker-format error in whichever serialisation the caller
// asked for (?xml, ?json, otherwise bencode).
//
// Views are required relative to __DIR__ so this file works the same whether
// it's loaded from phoenix.php's file scope or from a function body.

function tracker_error(string $error): never {
	if ( isset($_GET['xml']) ) {
		require_once __DIR__.'/../views/xml.error.php';
		header('Content-Type: text/xml');
		echo view_error_xml($error);
	} else if ( isset($_GET['json']) ) {
		require_once __DIR__.'/../views/json.error.php';
		header('Content-Type: application/json');
		echo view_error_json($error);
	} else {
		require_once __DIR__.'/../views/bencode.error.php';
		header('Content-Type: text/plain; charset=ISO-8859-1');
		echo view_error_bencode($error);
	}
	exit(2);
}
