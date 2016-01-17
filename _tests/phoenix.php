<?php

////	Glob Recursive Function
// Glob Recursively to a Pattern
function glob_recursive($Pattern, $Flags = 0, $Strip_Underscore = false) {
	$Return = array();
	// Search in the Current Directory
	foreach ( glob($Pattern, $Flags) as $File) {
		if (
			!$Strip_Underscore ||
			strpos($File, '/_') === false
		) {
			$Return[] = realpath($File);
		}
	}
	// FOREACHDIRECTORY
	// Search in ALL sub-directories.
	foreach ( glob(dirname($Pattern).'/*', GLOB_ONLYDIR | GLOB_NOSORT) as $Directory ) {
		// This is a recursive function.
		// Usually, THIS IS VERY BAD.
		// For searching recursively however,
		// it does make some sense.
		if (
			!$Strip_Underscore ||
			strpos($Directory, '/_') === false
		) {
			$Return = array_merge($Return, glob_recursive($Directory.'/'.basename($Pattern), $Flags, $Strip_Underscore));
		}
	} // FOREACHDIRECTORY
	return $Return;
}

function require_all_once($Directory) {
	foreach (glob_recursive($Directory.'*.php') as $File) {
		require_once $File;
	}
}

require_once __DIR__.'/../_phoenix.php';
require_all_once(__DIR__.'/phoenix/');
