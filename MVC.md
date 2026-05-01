# Phoenix 4 MVC Rewrite

## General
- Stay procedural and keep one function per file.
- Update tests with changes.

## Views

All output/presentation logic. Organized by format type.

### Bencode Protocol Views (BitTorrent wire format)
```
src/
  views/
    bencode.error.php       # ✓ CREATED: view_error_bencode($error)
                            # Tracker error responses (d14:failure reason...e)
                            # Replaces: function.tracker.error.php (inline echo)
                            # Used by: all endpoints on fatal errors
    
    bencode.announce.php    # ✓ CREATED: view_announce_bencode($counts, $settings, $rows, $compact, $no_peer_id)
                            # Announce responses (peer lists, interval, counts)
                            # Replaces: once.announce.torrent.php (inline bencode building)
                            # Used by: announce.php
    
    bencode.scrape.php      # ✓ CREATED: view_scrape_bencode($scrape)
                            # Scrape responses (per BEP 15)
                            # Replaces: function.scrape.render.bencode.php
                            # Used by: scrape.php (default format)
```

#### Bencode Implementation Notes
- **Error format**: `d14:failure reason<len>:<msg>e` — single key-value dict
- **Announce format**: Dict with keys in lexicographic order:
  - `complete` (int), `incomplete` (int), `interval` (int), `min interval` (int), `peers`
  - Compact mode (BEP 23/7): `peers` is binary string (6 bytes/IPv4, 18 bytes/IPv6), `peers6` for IPv6
  - Non-compact mode: `peers` is list of dicts, each with `ip`, `port`, optional `peer id` (20 bytes raw)
- **Scrape format**: `d5:filesd20:<raw_hash>d8:complete<i>e10:downloaded<i>e10:incomplete<i>eeee`
  - Info_hash is raw 20-byte binary (hex2bin), NOT 40-char hex
  - Multiple torrents nest more hash→stats dicts inside the `files` dict
- **Bencode rules**: Keys must be sorted lexicographically, strings are `<len>:<data>`, integers are `i<num>e`, lists are `l...e`, dicts are `d...e`
- **Critical**: Never echo directly in view functions — return the bencode string. Caller handles output + exit.

### XML Views (simple format converters)
```
src/
  views/
    xml.announce.php        # ✓ CREATED: view_announce_xml($counts, $settings, $rows)
                            # Announce response as XML (for debugging/monitoring)
                            # NEW - did not previously exist
                            # Used by: announce.php?xml (if implemented)
    
    xml.index.php           # ✓ CREATED: view_index_xml($index)
                            # Torrent index as XML
                            # Replaces: function.index.render.xml.php
                            # Used by: index.php?xml
    
    xml.scrape.php          # ✓ CREATED: view_scrape_xml($scrape)
                            # Scrape data as XML
                            # Replaces: function.scrape.render.xml.php
                            # Used by: scrape.php?xml
    
    xml.stats.php           # ✓ CREATED: view_stats_xml($stats, $settings)
                            # Tracker stats as XML
                            # Replaces: function.stats.render.xml.php
                            # Used by: scrape.php?stats&xml
```

### HTML Views (full pages and forms)
```
src/
  views/
    html.index.php          # ✓ CREATED: view_index_html($index)
                            # Public torrent listing
                            # Replaces: function.index.render.html.php
                            # Used by: index.php (default format)
    
    html.stats.php          # ✓ CREATED: view_stats_html($stats, $settings)
                            # Tracker statistics page
                            # Replaces: function.stats.render.html.php
                            # Used by: scrape.php?stats (default format)
    
    html.login.php          # ✓ CREATED: view_login_html($show_error)
                            # Admin login form
                            # Replaces: function.auth.render.login.form.php
                            # Used by: admin.php (when auth required)
    
    html.install.php        # ✓ CREATED: view_install_html($settings_writable, $install_error, $form)
                            # First-run installation form
                            # Replaces: src/includes/install-form.php
                            # Used by: admin.php (when no phoenix.custom.php exists)
    
    html.admin.php          # ✓ CREATED: view_admin_html($settings, $tables_installed, $database_size, $message, $show_installed)
                            # Admin panel with diagnostics, setup, optimize, clean
                            # Replaces: src/includes/admin-panel.php
                            # Used by: admin.php (main admin UI)
```

### Standalone Views (non-bootstrapped)
```
public/
  magnet.php                # Client-side magnet link generator
                            # Self-contained HTML + JavaScript
                            # Does NOT bootstrap src/phoenix.php
                            # Pure client-side .torrent parsing + magnet generation
```

### Notes
- **Bencode views** are the core tracker protocol. They currently mix logic and presentation (especially announce, which builds bencode inline). Extract to dedicated view files.
- **XML views** are simple string-building wrappers that convert arrays to XML. Extract to dedicated view files for consistency.
- **JSON output** uses built-in `json_encode()` directly in controllers - no separate view files needed.
- **HTML views** range from simple (index, stats) to complex (admin panel). The admin panel and install form are currently in `src/includes/`; move to `src/views/html.*.php`.
- **Standalone views** like `magnet.php` don't use the tracker at all — pure UI utilities. Leave in `public/` but document here for completeness.
- All views receive normalized data arrays (never raw DB results or `$_GET`/`$_POST`).
- Views are responsible for setting their own `Content-Type` headers where applicable.

## Model

All database operations. Organized by domain/table. One function per file, each returns results or false.

### Peer Model (peers table)
```
src/
  model/
    peer.select.php         # ✓ CREATED: peer_select($connection, $settings, $peer)
                            # SELECT single peer by info_hash + peer_id
                            # Replaces: function.peer.select.php
                            # Returns: array|false|null (row or null if not found)
                            # Used by: announce event handling
    
    peer.insert.php         # ✓ CREATED: peer_insert($connection, $settings, $time, $peer)
                            # REPLACE INTO peer (insert or update all fields)
                            # Replaces: function.peer.new.php
                            # Used by: announce (new peers, state changes)
    
    peer.update.php         # ✓ CREATED: peer_update($connection, $settings, $time, $peer)
                            # UPDATE timestamp + transfer stats only
                            # Replaces: function.peer.access.php
                            # Used by: announce (re-announce, no state change)
    
    peer.delete.php         # ✓ CREATED: peer_delete($connection, $settings, $peer)
                            # DELETE single peer by info_hash + peer_id
                            # Replaces: function.peer.delete.php
                            # Used by: announce?event=stopped
    
    peers.select.active.php # ✓ CREATED: peers_select_active($connection, $settings, $peer, $stale_threshold, $strategy)
                            # SELECT active peers for a torrent (for announce response)
                            # Replaces: function.peers.select.active.php
                            # WHERE: info_hash, updated > stale_threshold
                            # ORDER/LIMIT: per strategy (seeders-first, random, etc.)
                            # Returns: array of peer rows
    
    peers.count.swarm.php   # ✓ CREATED: peers_count_swarm($connection, $settings, $info_hash, $stale_threshold)
                            # SELECT COUNT seeders/leechers for one torrent
                            # Replaces: function.peer.swarm.counts.php
                            # Returns: array{complete: int, incomplete: int}
                            # Used by: announce response (interval calculation)
    
    peers.count.rate.php    # ✓ CREATED: peers_count_rate($connection, $settings, $peer, $threshold)
                            # SELECT COUNT announces from one IP in time window
                            # Replaces: inline query in function.announce.check.rate.limit.php
                            # Returns: int count
                            # Used by: rate limiting
    
    peers.scrape.php        # ✓ CREATED: peers_scrape($connection, $settings, $where_clause)
                            # SELECT aggregated peer counts per torrent (for scrape)
                            # Replaces: function.scrape.query.peers.php
                            # GROUP BY info_hash, returns seeders/leechers per torrent
                            # Used by: scrape.php (specific torrents)
    
    peers.scrape.all.php    # ✓ CREATED: peers_scrape_all($connection, $settings)
                            # SELECT aggregated peer counts for ALL torrents
                            # Replaces: function.scrape.query.all.peers.php
                            # Used by: scrape.php (full scrape)
    
    peers.clean.php         # ✓ CREATED: peers_clean($connection, $settings, $threshold)
                            # DELETE stale peers (updated < threshold)
                            # Extracted from function.task.clean.php (inline, multi-table)
                            # WHERE: updated < time - (3 * interval), OR test sentinels
                            # Used by: cron cleanup (via task_clean)
```

### Torrent Model (torrents table)
```
src/
  model/
    torrent.increment.downloads.php  # ✓ CREATED: torrent_increment_downloads($connection, $settings, $info_hash)
                                     # INSERT ... ON DUPLICATE KEY UPDATE downloads counter
                                     # Replaced: function.peer.completed.php
                                     # Used by: announce?event=completed
    
    torrents.select.allowed.php  # ✓ CREATED: torrents_select_allowed($connection, $settings)
                                 # SELECT all info_hashes (for closed tracker)
                                 # Replaced: function.tracker.allowed.php
                                 # Returns: array of info_hash strings
                                 # Used by: phoenix.php (whitelist check)
    
    torrents.select.listed.php   # ✓ CREATED: torrents_select_listed($connection, $settings)
                                 # SELECT listed torrents with peer counts (for index)
                                 # Extracted from: once.index.torrents.php (inline query)
                                 # WHERE: listed=1
                                 # LEFT JOIN peers, GROUP BY info_hash
                                 # Returns: array of torrent rows with seeders/leechers/peers/traffic
    
    torrents.scrape.php     # ✓ CREATED: torrents_scrape($connection, $settings, $where_clause)
                            # SELECT torrent metadata (info_hash, size, downloads) for scrape
                            # Replaced: function.scrape.query.torrents.php
                            # WHERE: info_hash IN (...)
                            # Used by: scrape.php (specific torrents)
    
    torrents.scrape.all.php # SELECT ALL torrent metadata for full scrape
                            # Currently: function.scrape.query.all.torrents.php
                            # Used by: scrape.php?full_scrape
    
    torrents.clean.php      # DELETE test/sentinel torrents
                            # Currently: function.task.clean.php (inline, multi-table)
                            # WHERE: test sentinels
```

### Stats Model (cross-table aggregations)
```
src/
  model/
    stats.peers.php         # SELECT total seeders/leechers/peers/torrents
                            # Currently: function.stats.fetch.peer.counts.php
                            # Aggregates from peers table
                            # Returns: array{seeders, leechers, peers, torrents}
    
    stats.downloads.php     # SELECT total downloads + traffic from torrents table
                            # Currently: function.stats.fetch.download.totals.php
                            # Returns: array{downloads, traffic}
```

### Task Model (tasks table)
```
src/
  model/
    task.log.php            # REPLACE INTO task log entry (name PK, timestamp value)
                            # Currently: function.task.log.php
                            # Used by: install, clean, optimize
    
    task.clean.php          # DELETE test/sentinel rows from tasks table
                            # Currently: function.task.clean.php (inline, multi-table)
```

### Database Management
```
src/
  model/
    db.create.php           # CREATE TABLE (peers, torrents, tasks)
                            # Currently: function.mysqli.create.database.php
                            # Used by: installer, admin setup
    
    db.drop.php             # DROP TABLE IF EXISTS
                            # Currently: function.mysqli.drop.table.php
                            # Used by: admin reset
    
    db.optimize.php         # CHECK/ANALYZE/REPAIR/OPTIMIZE tables
                            # Currently: function.task.optimize.php
                            # Used by: admin panel, cron
```

### Utility Helpers (not domain models, but DB utilities)
```
src/
  model/
    db.fetch.once.php       # Execute query, return first row as assoc array
                            # Currently: function.mysqli.fetch.once.php
                            # Helper for single-row queries
    
    db.fetch.array.php      # Execute query, return single column as flat array
                            # Currently: function.mysqli.array.build.php
                            # Helper for SELECT column lists
```

### Notes
- **All model functions** accept `$connection`, `$settings`, and domain parameters. Never read `$_GET`/`$_POST` directly.
- **Return types**: `array` (rows/results), `false` (query failed), `null` (valid query but no rows), `true` (success for non-SELECT), `int` (affected rows when relevant).
- **Error handling**: Model functions should NOT call `tracker_error()` — return `false` and let the controller decide whether to error or continue. Exception: queries that are always fatal in context can error inline (document this).
- **Query building**: String concatenation is current practice. WHERE clause builders (e.g. `scrape.build.where.clause.php`) stay as controller/logic helpers, not models.
- **Sanitization happens before model**: Models trust their inputs are already sanitized. The `info_hash` and `peer_id` are hex strings (sanitized by `maybe_binary_to_hex` at the boundary).
- **Multi-table cleanup**: `task.clean.php` currently deletes from 3 tables in one call. Consider splitting into `peers.clean.php`, `torrents.clean.php`, `tasks.clean.php` or keep as orchestrator.
- **Test sentinels**: Cleanup queries include `OR field='DELETEME'` / `OR field LIKE '__TEST_%'` patterns for test isolation. Preserve these in extracted model functions.

## Controller

HTTP request handlers in `public/`. Each file orchestrates: sanitize input → call model → call view. Logic from `src/onces/` gets absorbed directly into these files.

### `public/announce.php` - BitTorrent Announce Endpoint (BEP 3)
**Current flow:**
1. Bootstrap (`require_once ../src/phoenix.php`)
2. Sanitize tracker params (`sanitize_tracker_params()`)
3. Validate info_hash (40 hex chars)
4. Check torrent allowed (if closed tracker)
5. Validate peer_id (40 hex chars)
6. `once.sanitize.announce.address.php` → resolve IP addresses & ports
7. Parse optional params (`peer_parse_announce_optional()`)
8. Rate limiting (`announce_check_rate_limit()`)
9. `once.announce.peer.event.php` → handle peer events
10. Cleanup (if probabilistic)
11. `once.announce.torrent.php` → build response

**Refactored flow (absorb onces):**
```php
// Bootstrap
require_once __DIR__.'/../src/phoenix.php';

// Sanitization & validation
$peer = sanitize_tracker_params();
validate_info_hash($peer['info_hash'], $allowed_torrents, $settings);
validate_peer_id($peer['peer_id']);

// Address resolution (absorb once.sanitize.announce.address.php)
$addresses = peer_address_candidates($settings, $_GET, $_SERVER);
$resolved = peer_resolve_addresses($addresses);
$peer = array_merge($peer, $resolved);
validate_addresses($peer); // ensure ipv4/portv4 or ipv6/portv6

// Optional params
$peer = array_merge($peer, peer_parse_announce_optional($_GET, $settings));

// Rate limiting
announce_check_rate_limit($connection, $settings, $peer, $time);

// Event handling (absorb once.announce.peer.event.php)
$event = $_GET['event'] ?? null;
$peer['old'] = peer_select($connection, $settings, $peer); // MODEL

if ($event === 'stopped') {
    peer_delete($connection, $settings, $peer); // MODEL
    phoenix_hook('peer.stopped', $connection, $settings, $time, $peer);
    exit;
}

if ($event === 'completed') {
    $peer['state'] = 1;
    torrent_upsert($connection, $settings, $peer); // MODEL (increment downloads)
    phoenix_hook('download.complete', $connection, $settings, $time, $peer);
}

if (peer_changed($peer, $peer['old'])) {
    peer_insert($connection, $settings, $time, $peer); // MODEL (REPLACE)
    phoenix_hook($peer['old'] ? 'peer.change' : 'peer.new', $connection, $settings, $time, $peer);
} else {
    peer_update($connection, $settings, $time, $peer); // MODEL (UPDATE timestamp only)
    phoenix_hook('peer.access', $connection, $settings, $time, $peer);
}

// Cleanup
if (!$settings['clean_with_cron'] && $chance <= $settings['clean_with_requests']) {
    task_clean($connection, $settings, $time); // MODEL
}

// Build response (absorb once.announce.torrent.php)
$stale_threshold = $time - ($settings['announce_interval'] + $settings['min_interval']);
$counts = peers_count_swarm($connection, $settings, $peer['info_hash'], $stale_threshold); // MODEL
$strategy = peer_select_strategy($peer, $counts['complete'], $counts['incomplete'], $settings);
$rows = peers_select_active($connection, $settings, $peer, $stale_threshold, $strategy); // MODEL

// Render (VIEW - bencode.announce.php)
// Note: BitTorrent protocol requires bencode, but XML/JSON can be useful for debugging
if (isset($_GET['xml'])) {
    header('Content-Type: text/xml');
    echo view_announce_xml($peer, $counts, $rows, $settings); // VIEW
} else if (isset($_GET['json'])) {
    header('Content-Type: application/json');
    echo json_encode(['peer' => $peer, 'counts' => $counts, 'rows' => $rows]);
} else {
    echo view_announce_bencode($peer, $counts, $rows, $settings); // VIEW
}
```

**Business logic helpers used (not model/view):**
- `sanitize_tracker_params()` - parse/sanitize `$_GET` into `$peer` array
- `validate_info_hash()`, `validate_peer_id()`, `validate_addresses()` - input validation
- `peer_address_candidates()` - gather candidate IPs from `$_GET`, `$_SERVER`
- `peer_resolve_addresses()` - parse IPv4/IPv6 addresses with ports
- `peer_parse_announce_optional()` - parse `numwant`, `compact`, `no_peer_id`, etc.
- `peer_changed()` - compare old/new peer state to decide insert vs update
- `peer_select_strategy()` - decide ORDER BY + WHERE for peer selection
- `phoenix_hook()` - call user hooks if enabled

---

### `public/scrape.php` - BitTorrent Scrape Endpoint (BEP 15) + Stats
**Current flow:**
1. Bootstrap
2. Sanitize tracker params
3. **IF `?stats`**: fetch stats, render (HTML/JSON/XML)
4. **ELSE IF** specific torrents: build WHERE, query, render bencode scrape
5. **ELSE IF** full scrape allowed: query all, render bencode scrape
6. **ELSE**: error

**Refactored flow (inline logic):**
```php
require_once __DIR__.'/../src/phoenix.php';

$peer = sanitize_tracker_params();

// STATS mode
if (isset($_GET['stats'])) {
    $peer_counts = stats_peers($connection, $settings); // MODEL
    $download_totals = stats_downloads($connection, $settings); // MODEL
    $stats = stats_merge($peer_counts, $download_totals);
    
    if (!$stats) tracker_error('Unable to get stats.');
    
    if (isset($_GET['xml'])) {
        header('Content-Type: text/xml');
        echo view_stats_xml($stats); // VIEW
    } else if (isset($_GET['json'])) {
        header('Content-Type: application/json');
        echo json_encode($stats);
    } else {
        echo view_stats_html($stats, $settings); // VIEW
    }
    exit;
}

// SCRAPE mode (specific torrents)
if ($peer['info_hash'] && (
    $settings['open_tracker'] || 
    in_array($peer['info_hash'], $allowed_torrents)
)) {
    $where = scrape_build_where_clause($peer['info_hashes']);
    $scrape = scrape_initialize_results($peer['info_hashes']);
    
    $peers = peers_scrape($connection, $settings, $where); // MODEL
    $torrents = torrents_scrape($connection, $settings, $where); // MODEL
    
    if (!$peers || !$torrents) tracker_error('Unable to scrape for that torrent.');
    
    $scrape = scrape_merge_results($peers, $torrents, $scrape);
    echo view_scrape_bencode($scrape); // VIEW
    exit;
}

// FULL SCRAPE mode
if ($settings['full_scrape']) {
    $peers = peers_scrape_all($connection, $settings); // MODEL
    $torrents = torrents_scrape_all($connection, $settings); // MODEL
    
    if (!$peers || !$torrents) tracker_error('Unable to scrape for that torrent.');
    
    $scrape = scrape_merge_results($peers, $torrents);
    echo view_scrape_bencode($scrape); // VIEW
    exit;
}

// Not allowed
tracker_error($peer['info_hash'] ? 'Torrent is not allowed.' : 'Tracker scraping is not allowed.');
```

**Business logic helpers:**
- `scrape_build_where_clause()` - build WHERE clause for multiple info_hashes
- `scrape_initialize_results()` - pre-fill zeroed scrape entries for requested hashes
- `scrape_merge_results()` - merge peer counts + torrent metadata into scrape array
- `stats_merge()` - merge peer counts + download totals

---

### `public/index.php` - Public Torrent Index
**Current flow:**
1. Bootstrap
2. Check `$settings['public_index']` or error
3. `once.index.torrents.php` → query + render

**Refactored flow:**
```php
require_once __DIR__.'/../src/phoenix.php';

if (!$settings['public_index']) {
    tracker_error('Index is not public.');
}

// Query (absorb once.index.torrents.php query)
$index = torrents_select_listed($connection, $settings); // MODEL
if (!$index) tracker_error('Unable to get index.');

// Render
if (isset($_GET['xml'])) {
    header('Content-Type: text/xml');
    echo index_render_xml($index); // inline function
} else ifview_index_xml($index); // VIEW
    header('Content-Type: application/json');
    echo json_encode($index);
} else {
    echo view_index_html($index); // VIEW
}
```

**Business logic helpers:**
- None (just model + view)

---

### `public/admin.php` - Admin Panel & Installer
**Current flow:**
1. Bootstrap paths (before DB connection for installer)
2. **IF no config exists**: `once.install.php` → render install form
3. **ELSE**: full bootstrap, `once.auth.php` → authenticate
4. Process POST actions (setup, clean, optimize)
5. Render admin panel

**Refactored flow:**
```php
// Pre-bootstrap (paths only, no DB)
$settings['root'] = __DIR__.'/../';
$settings['functions'] = $settings['root'].'src/functions/';
// ...
require_once $settings['functions'].'function.tracker.error.php';

$config_path = $settings['settings'].'phoenix.custom.php';

// INSTALLER MODE (absorb once.install.php logic)
if (!is_readable($config_path)) {
    error_reporting(0);
    $settings_writable = is_writable($settings['settings']);
    $install_error = null;
    
    if ($_POST['process'] === 'install' && $settings_writable) {
        $config = install_sanitize_post($_POST);
        $db_test = install_test_connection($config);
        if ($db_test === true) {
            install_build_config($config); // writes phoenix.custom.php
            header('Location: admin.php?installed=1');
            exit;
        }
        $install_error = $db_test; // error string
    }
    
    echo view_install_html($settings_writable, $install_error, $_POST); // VIEW
    exit;
}

// NORMAL MODE - full bootstrap
require_once __DIR__.'/../src/phoenix.php';

// AUTH (absorb once.auth.php logic)
if (!empty($settings['admin_password'])) {
    session_start();
    
    if (isset($_GET['logout'])) {
        auth_handle_logout();
    }
    
    if (!auth_is_authenticated()) {
        $login_error = isset($_POST['process']) && $_POST['process'] === 'login';
        if ($login_error && auth_verify_login($settings)) {
            auth_set_authenticated();
            header('Location: '.$_SERVER['REQUEST_URI']);
            exit;
        }
        echo view_login_html($login_error); // VIEW
        exit;
    }
}

// PROCESS ACTIONS
$process = htmlentities($_POST['process'] ?? '', ENT_QUOTES, 'UTF-8');

if ($process === 'setup') {
    if ($settings['db_reset']) db_drop_all($connection, $settings); // MODEL
    db_create($connection, $settings); // MODEL
    task_log($connection, $settings, 'install', $time); // MODEL
    $Message = 'Your MySQL Tracker Database has been setup.';
}

if ($process === 'clean') {
    task_clean($connection, $settings, $time); // MODEL
    $Message = 'The peers list has been cleaned.';
}

if ($process === 'optimize') {
    db_optimize($connection, $settings, $time); // MODEL
    $Message = 'Your MySQL Tracker Database has been optimized.';
}

// RENDER PANEL
echo view_admin_html($connection, $settings, $Message ?? null); // VIEW
```

**Business logic helpers:**
- `install_sanitize_post()` - sanitize installer form input
- `install_test_connection()` - test DB credentials before writing config
- `install_build_config()` - write `phoenix.custom.php`
- `auth_*()` functions - session-based authentication

---

### `public/magnet.php` - Magnet Link Generator (non-tracker utility)
**No changes needed** - already self-contained, doesn't bootstrap Phoenix.

---

### Business Logic Functions (neither model nor view)

These stay in `src/functions/` as helpers called by controllers:

**Input Sanitization & Validation:**
- `sanitize_tracker_params()` - parse `$_GET` into normalized `$peer` array
- `sanitize_maybe_binary_to_hex()` - convert binary info_hash/peer_id to hex
- `validate_*()` - validation checks (can be new or absorbed into sanitize functions)
- `install_sanitize_post()` - sanitize installer form

**Address/IP Handling:**
- `peer_address_candidates()` - gather candidate IPs from request
- `peer_resolve_addresses()` - parse IPv4/IPv6 with ports
- `parse_ipv4()`, `parse_ipv6()` - low-level IP parsing

**Peer Logic:**
- `peer_parse_announce_optional()` - parse announce query string params
- `peer_changed()` - diff old/new peer to decide insert vs update
- `peer_select_strategy()` - decide peer selection strategy (ORDER BY)
- `peer_format_bencode()` - format single peer as bencode dict
- `peers_format_compact()` - format peer list as compact binary

**Scrape Helpers:**
- `scrape_build_where_clause()` - build WHERE for multiple info_hashes
- `scrape_initialize_results()` - pre-fill zero results for missing torrents
- `scrape_merge_results()` - merge peer/torrent query results

**Stats Helpers:**
- `stats_merge()` - merge peer counts + download totals

**Auth:**
- `auth_is_authenticated()`, `auth_verify_login()`, `auth_set_authenticated()`, `auth_handle_logout()`

**Hooks:**
- `phoenix_hook()` - call user-defined hook scripts

**Install:**
- `install_build_config()` - write config file

**Rate Limiting:**
- `announce_check_rate_limit()` - enforce per-IP announce limits

---

### What Happens to `src/onces/`?

All onces get absorbed into `public/` controllers:
- `once.announce.peer.event.php` → absorbed into `announce.php`
- `once.announce.torrent.php` → absorbed into `announce.php`
- `once.sanitize.announce.address.php` → absorbed into `announce.php`
- `once.index.torrents.php` → absorbed into `index.php`
- `once.auth.php` → absorbed into `admin.php`
- `once.install.php` → absorbed into `admin.php`
- `once.db.connect.php` → stays in `src/phoenix.php` (bootstrap)
- `once.scrape.torrent.php` → doesn't exist / unused

The `src/onces/` directory can be deleted after refactoring.

---

### Notes
- **Controllers are thin orchestrators** - just sanitize → model → view flow.
- **No routing layer** - each `public/*.php` is a single endpoint.
- **Validation errors call `tracker_error()`** - bencode error response + exit.
- **Business logic stays in `src/functions/`** - controllers call these helpers.
- **Hooks remain procedural** - `phoenix_hook()` checks file existence and includes it.
- **Keep top-to-bottom readability** - inline the onces logic directly so you can read the flow without jumping between files.
