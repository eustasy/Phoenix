<?php

declare(strict_types=1);

////	peer_changed
// Returns true if any of the announce-relevant fields differ between the
// new peer state and the previous row, or if there is no previous row.
function peer_changed(array $peer, array|false $old): bool
{
    return
        ! $old ||
        $peer['ipv4'] != $old['ipv4'] ||
        $peer['ipv6'] != $old['ipv6'] ||
        $peer['portv4'] != $old['portv4'] ||
        $peer['portv6'] != $old['portv6'] ||
        $peer['state'] != $old['state'];
}
