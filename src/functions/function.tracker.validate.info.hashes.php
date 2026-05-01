<?php
////	tracker_validate_info_hashes
// Check if all requested info_hashes are in the allowed torrents list.
// Returns true if all hashes are allowed, false otherwise.
// Used by scrape.php to enforce access control for multi-hash BEP 15 scrapes.

function tracker_validate_info_hashes($info_hashes, $allowed_torrents) {
	foreach ($info_hashes as $hash) {
		if (!in_array($hash, $allowed_torrents)) {
			return false;
		}
	}
	return true;
}
