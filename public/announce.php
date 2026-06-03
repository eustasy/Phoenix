<?php

declare(strict_types=1);

////	announce.php
// BitTorrent announce endpoint (BEP 3)
// Handles peer registration, state updates, and peer list responses

// Allow cross-origin reads — browser-based clients announce here. Sent before
// bootstrap so error responses carry it too. Scoped to the public read endpoints
// (announce/scrape/index); the admin panel deliberately omits it.
header('Access-Control-Allow-Origin: *');

require_once __DIR__.'/../src/phoenix.php';
require_once __DIR__.'/../src/controller/announce.php';

echo announce_controller($connection, $settings, $time, $allowed_torrents ?? []);
