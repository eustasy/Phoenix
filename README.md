# Phoenix v.2.0
A modern fork of [PeerTracker](https://github.com/JonnyJD/peertracker), a lightweight PHP/SQL BitTorrent Tracker.
It is not backwards compatible in most ways, and drops SQLite support.

## What Do You Need?

### Required
* Apache, Nginx, OR lighttpd.
* [A supported version of PHP](http://php.net/supported-versions.php)
* MySQL >= 4.1 OR MariaDB

## Install Guide
1. Copy `settings.default.php` to `settings.custom.php`
2. Edit the variables in `settings.custom.php`
2. Upload all the `.php` files to your server.
4. Load `admin.php` in your browser and run the `Setup` option.
5. Add `$settings['db_reset'] = false;` to the end of `settings.custom.php`, OR delete `admin.php` from your server.
