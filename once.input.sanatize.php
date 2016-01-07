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
