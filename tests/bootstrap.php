<?php

declare(strict_types=1);

// PHPUnit bootstrap: connects to the configured database, ensures the TESTING_-prefixed
// tables exist, and exposes the connection/settings/time triple as $GLOBALS so each
// PhoenixTestCase can pick them up in setUpBeforeClass().

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../src/phoenix.php';

// Override db_prefix so tests can't touch production tables.
$settings['db_prefix'] = $settings['db_prefix'].'TESTING_';

require_once __DIR__.'/../src/model/db.create.php';
if (! db_create($connection, $settings)) {
    fwrite(STDERR, 'Failed to set up test database.'.PHP_EOL);
    exit(1);
}

$GLOBALS['phoenix_connection'] = $connection;
$GLOBALS['phoenix_settings'] = $settings;
$GLOBALS['phoenix_time'] = $time;
