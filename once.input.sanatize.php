<?php

////	admin.php
// $_POST['process']
// $_GET['message']

////	announce.php
// $_GET['info_hash']
// $_GET['peer_id']
// $_GET['ipv4']
// $_GET['ipv6']
// $_GET['port']
// $_GET['portv4']
// $_GET['portv6']

////	function.peer.access.php
// $_GET['left']
// $_GET['info_hash']
// $_GET['peer_id']

////	function.peer.completed.php
// $_GET['info_hash']

////	function.peer.delete.php
// $_GET['info_hash']
// $_GET['peer_id']

////	function.peer.event.php
// $_GET['info_hash']
// $_GET['peer_id']
// $_GET['event']
// $_GET['ipv4']
// $_GET['ipv6']
// $_GET['portv4']
// $_GET['portv6']

////	function.peer.new.php
// $_GET['ipv4']
// $_GET['ipv6']
// $_GET['portv4']
// $_GET['portv6']
// $_GET['info_hash']
// $_GET['peer_id']
// $_GET['left']

////	function.torrent.announce.php
// $_GET['info_hash']
// $_GET['numwant']
// $_GET['compact']
// $_GET['no_peer_id']

////    function.torrent.scrape.php
// $_GET['info_hash']
// $_GET['xml']
// $_GET['json']

////    function.tracker.scrape.php
// $_GET['xml']
// $_GET['json']

////    function.tracker.stats.php
// $_GET['xml']
// $_GET['json']

////    once.announce.ip.php
// $_GET['ip']
// $_GET['ipv4']
// $_GET['ipv6']
// $_SERVER['REMOTE_ADDR']
// $_SERVER['HTTP_CLIENT_IP']
// $_SERVER['HTTP_X_FORWARDED_FOR']
// $_GET['port']

////    once.announce.optional.php
// $_GET['left']
// $_GET['compact']
// $_GET['no_peer_id']
// $_GET['numwant']

////    scrape.php
// $_GET['stats']
// $_GET['info_hash']



// IF BINARY
if (
    isset($_GET['info_hash']) &&
    strlen($_GET['info_hash']) == 20
) {
    $_GET['info_hash'] = bin2hex($_GET['info_hash']);
}
if (
    isset($_GET['peer_id']) &&
    strlen($_GET['peer_id']) == 20
) {
    $_GET['peer_id'] = bin2hex($_GET['peer_id']);
}
// END IF BINARY
