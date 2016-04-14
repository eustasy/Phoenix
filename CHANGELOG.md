# Phoenix Changelog

## v.3.1 - 14/04/2016 - Unicorn
* IMPROVES: Switched to MIT Licensing.
* IMPROVES: Documented how to set up cron jobs ([#28](https://github.com/eustasy/phoenix/issues/28)).
* IMPROVES: Documented where custom configuration _should_ be stored.
* IMPROVES: De-duplicate test initialization and database initialization ([#27](https://github.com/eustasy/phoenix/issues/27)).
* IMPROVES: Automated testing now uses the same database, and respects prefixes, and custom usernames and database names.
* IMPROVES: Update default settings to not allow database resets.
* IMPROVES: Some tests are now stricter.
* BUGFIX: Fixes table-size erroring when no tables are deployed.

## v.3.0 - 21/01/2016 - Sanitized
* FEATURE: Adds multi-torrent scraping ([#19](https://github.com/eustasy/phoenix/issues/19)).
* FEATURE: Optionally replace tasks with cron jobs ([#22](https://github.com/eustasy/phoenix/issues/22)).
* FEATURE: Adds unit-tests for functions ([#17](https://github.com/eustasy/phoenix/issues/17)).
* FEATURE: Adds normalized checks.
* IMPROVES: Sanitizes super-globals before use ([#16](https://github.com/eustasy/phoenix/issues/16)).
* IMPROVES: Documented hook files ([#23](https://github.com/eustasy/phoenix/issues/23)).
* IMPROVES: Removes duplicated variables ([#20](https://github.com/eustasy/phoenix/issues/20)).
* IMPROVES: Moved to puff-style structure ([#18](https://github.com/eustasy/phoenix/issues/18)).
* IMPROVES: IP detection, especially when reporting IPv4 _and_ IPv6.
* IMPROVES: Adds database size to `admin.php`
* BUGFIX: Variables should be named in international english ([#21](https://github.com/eustasy/phoenix/issues/21)).
* BUGFIX: Fix compact reporting for IPv4.
* BUGFIX: Fix full-tracker scraping.
* REMOVES: Nothing.

## v.2.0 - 20/08/2015 - Unification
* FEATURE: Adds support for IPv6 ([#3](https://github.com/eustasy/phoenix/issues/3)).
* IMPROVES: More tasks are logged.
* BUGFIX: Task names being trimmed.
* BUGFIX: Task being duplicated.
* BUGFIX: Certain torrents binary hash is malformed due to a poorly implemented "verbose" mode ([#14](https://github.com/eustasy/phoenix/issues/14)).
* REMOVES: Verbose mode for torrent scraping. JSON and XML are still available.

## v.1.4 - 18/08/2015 - Totalitarian
* FEATURE: Add downloads totals ([#10](https://github.com/eustasy/phoenix/issues/10)).
* FEATURE: Add preliminary support for IPv6 ([#3](https://github.com/eustasy/phoenix/issues/3)).
* IMPROVES: Git ignores hooks or custom files.
* IMPROVES: Adds verbose option to torrent scraping for better display of bencoded content.
* BUGFIX: Fixes scrape counts of torrents by encoding hashes in their binary format.
* BUGFIX: Fixes issue where cleaning was never logged.

## v.1.3 - 16/02/2015 - Hexa
* BUGFIX: Fixes issue with escaping binary data by storing it all as Hexadecimal.

## v.1.2 - 31/12/2014 - Endpoints
* FEATURE: Support Endpoints, rather than just separate ports.

## v.1.1 - 31/12/2014 - Scraping By
* FEATURE: Adds JSON and XML output to scrapes and stats.
* FEATURE: Adds HEX info_hash support to announce.
* IMPROVES: Stop double-submissions on admin page.
* IMPROVES: Improves configuration defaults.
* BUGFIX: Fix broken scraping when requesting a torrent as a binary value.
* BUGFIX: Set correct default charset.

## v.1.0 - 28/12/2014 - No longer PeerTracker.
* A procedural re-write of PeerTracker in a modern format.
* Fixes numerous bugs and massively improves performance, modularity, and maintainability.

*****

# PeerTracker Changelog

## v0.1.3 - 01/20/2010
* BUGFIX: Failure to assign returned data from stripslashes.


## v0.1.2 - 11/18/2009
* BUGFIX: Garbage collection routine interval.

## v0.1.1 - 10/31/2009
* FEATURE: Tracker Statistics (peers, seeders, leechers, torrents) output via html, xml & json.
* FEATURE: Database Prefixes, allows multiple trackers to be ran from a single database.
* FEATURE: Support for persistent connections (via mysql or mysqli (php >= 5.3)).
* IMPROVES: Implemented support for full scrapes.
* IMPROVES: More efficient table rows.

## v0.1.0 - 10/24/2009
* FEATURE: Completed /announce and partial /scrape support.
