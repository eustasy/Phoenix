<?php

declare(strict_types=1);

////	Admin Panel & Installer
// Protected by a bcrypt password (admin_login_controller) with a hardened
// session and a failed-login throttle — but ONLY when admin_password is set.
// With it empty, auth is skipped: set a password, and remove or relocate this
// file once setup is done. See the hardening notes in README.md / APACHE.md /
// NGINX.md (post-setup removal, admin rate-limiting).

// Bootstrap tracker_error before any DB work so the installer-mode path
// below can use it without going through phoenix.php's full DB connect.
require_once __DIR__.'/../src/functions/tracker.error.php';

// Composer autoloader for optional libraries — loaded here too because the
// installer mode below never reaches phoenix.php, yet 2FA enrolment needs the
// authenticatron class. Conditional, so installs without Composer still work.
$admin_autoload = __DIR__.'/../vendor/autoload.php';
if (is_readable($admin_autoload)) {
    require_once $admin_autoload;
}

////	Charset
// This panel only ever emits HTML, but phoenix.php (loaded below for normal
// mode) sets default_charset to iso-8859-1 for the binary tracker protocol. An
// HTTP charset header overrides the layout's <meta charset>, so without this
// the UTF-8 views — torrent names, the em-dash in flag notes — would be decoded
// as iso-8859-1. Set before any output so both installer and panel modes match.
header('Content-Type: text/html; charset=UTF-8');

$config_path = __DIR__.'/../config/phoenix.custom.php';
$config_exists = is_readable($config_path);

////	Installer Mode (no config file exists)
if (! $config_exists) {
    require_once __DIR__.'/../src/controller/admin.install.php';
    echo admin_install_controller($config_path);
    exit;
}

////	Normal Admin Mode (full bootstrap)
require_once __DIR__.'/../src/phoenix.php';

////	Authentication
require_once __DIR__.'/../src/controller/admin.login.php';
$login_output = admin_login_controller($settings);
if ($login_output !== null) {
    echo $login_output;
    exit;
}

////	Render admin panel
require_once __DIR__.'/../src/controller/admin.panel.php';
echo admin_panel_controller($connection, $settings, $time);
