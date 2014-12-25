<?php

// Enviroment Runtime //////////////////////////////////////////////////////////////////////////////

// load tracker core
require './phoenix.php';

// Verify Request //////////////////////////////////////////////////////////////////////////////////

// tracker statistics
if (isset($_GET['stats']))
{
	// open database
	peertracker::open();

	// display stats
	peertracker::stats();

	// close database
	peertracker::close();

	// exit immediately
	exit;
}

// strip auto-escaped data
if (get_magic_quotes_gpc()) $_GET['info_hash'] = stripslashes($_GET['info_hash']);

// 20-bytes - info_hash
// sha-1 hash of torrent being tracked
if (!isset($_GET['info_hash']) || strlen($_GET['info_hash']) != 20)
{
	// full scrape disabled
	if (!$_SERVER['tracker']['full_scrape']) exit;
	// full scrape enabled
	else unset($_GET['info_hash']);
}

// Handle Request //////////////////////////////////////////////////////////////////////////////////

// open database
peertracker::open();

// perform scrape
peertracker::scrape();

// close database
peertracker::close();

?>