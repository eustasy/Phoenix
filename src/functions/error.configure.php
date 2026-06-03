<?php

declare(strict_types=1);

////	error_configure
// Layers the operator's error settings on top of the bootstrap baseline (which
// already turned logging on and display off). Two knobs:
//
//   error_log — redirect PHP's error log to a file when set (empty = the
//               server/PHP default destination).
//   debug     — raise verbosity to E_ALL and surface errors on output for LOCAL
//               troubleshooting only.
//
// display_errors is never turned on in normal operation: tracker responses are
// bencode/binary, so a printed warning would corrupt the body and disclose
// internals. Enable `debug` only on a non-production instance.
/** @param PhoenixSettings $settings */
function error_configure(array $settings): void
{
    if ($settings['error_log'] !== '') {
        ini_set('error_log', $settings['error_log']);
    }

    if ($settings['debug']) {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
    }
}
