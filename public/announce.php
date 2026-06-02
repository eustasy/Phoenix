<?php

declare(strict_types=1);

////	announce.php
// BitTorrent announce endpoint (BEP 3)
// Handles peer registration, state updates, and peer list responses

require_once __DIR__.'/../src/phoenix.php';
require_once __DIR__.'/../src/controller/announce.php';

echo announce_controller($connection, $settings, $time, $allowed_torrents ?? []);
