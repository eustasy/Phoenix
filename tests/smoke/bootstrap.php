<?php

declare(strict_types=1);

// Smoke-suite bootstrap. Unlike tests/bootstrap.php this does NOT touch the
// database or load src/phoenix.php — the smoke tests only speak HTTP to a
// running `php -S` instance (see SMOKE_BASE_URL). All it needs is the autoloader
// for PHPUnit and its attributes.

require_once __DIR__.'/../../vendor/autoload.php';
