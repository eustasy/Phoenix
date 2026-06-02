<?php

declare(strict_types=1);

////	tracker_filter_info_hashes
// Returns the subset of $info_hashes that appear in $allowed_torrents,
// preserving the original order. Used by scrape.php on closed trackers to
// drop disallowed hashes from a multi-hash BEP 15 scrape rather than
// rejecting the whole request — disallowed entries are silently skipped
// and the caller replies with whatever's left.
//
// Returns an empty array when nothing in $info_hashes is allowed; the
// caller is responsible for treating that as a "not allowed" error.

function tracker_filter_info_hashes(array $info_hashes, array $allowed_torrents): array
{
    return array_values(array_intersect($info_hashes, $allowed_torrents));
}
