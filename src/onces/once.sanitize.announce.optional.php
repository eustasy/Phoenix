<?php

declare(strict_types=1);

require_once $settings['functions'].'function.peer.parse.announce.optional.php';

$peer = array_merge($peer, peer_parse_announce_optional($_GET, $settings));
