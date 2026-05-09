<?php
////	tracker_validate_info_hashes
// Check if all requested info_hashes are in the allowed torrents list.
// Returns true if all hashes are allowed, false otherwise.
// Used by scrape.php to enforce access control for multi-hash BEP 15 scrapes.

function tracker_filter_info_hashes(array $info_hashes, array $allowed_torrents): array {
	return array_values(array_intersect($info_hashes, $allowed_torrents));
}