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
    bencode.error.php       # Tracker error responses (d14:failure reason...e)
                            # Currently: function.tracker.error.php
                            # Used by: all endpoints on fatal errors
    
    bencode.announce.php    # Announce responses (peer lists, interval, counts)
                            # Currently: once.announce.torrent.php (inline bencode string building)
                            # Used by: announce.php
    
    bencode.scrape.php      # Scrape responses (per BEP 15)
                            # Currently: function.scrape.render.bencode.php
                            # Used by: scrape.php (default format)
```

### HTML Views (full pages and forms)
```
src/
  views/
    html.index.php          # Public torrent listing
                            # Currently: function.index.render.html.php
                            # Used by: index.php (default format)
    
    html.stats.php          # Tracker statistics page
                            # Currently: function.stats.render.html.php
                            # Used by: scrape.php?stats (default format)
    
    html.login.php          # Admin login form
                            # Currently: function.auth.render.login.form.php
                            # Used by: admin.php (when auth required)
    
    html.install.php        # First-run installation form
                            # Currently: src/includes/install-form.php
                            # Used by: admin.php (when no phoenix.custom.php exists)
    
    html.admin.php          # Admin panel with diagnostics, setup, optimize, clean
                            # Currently: src/includes/admin-panel.php
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
- **JSON/XML views** are simple `json_encode()` or string-building wrappers. Do not add view files, just do inline.
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
    peer.select.php         # SELECT single peer by info_hash + peer_id
                            # Currently: function.peer.select.php
                            # Returns: array|false|null (row or null if not found)
                            # Used by: announce event handling
    
    peer.insert.php         # REPLACE INTO peer (insert or update all fields)
                            # Currently: function.peer.new.php
                            # Used by: announce (new peers, state changes)
    
    peer.update.php         # UPDATE timestamp + transfer stats only
                            # Currently: function.peer.access.php
                            # Used by: announce (re-announce, no state change)
    
    peer.delete.php         # DELETE single peer by info_hash + peer_id
                            # Currently: function.peer.delete.php
                            # Used by: announce?event=stopped
    
    peers.select.active.php # SELECT active peers for a torrent (for announce response)
                            # Currently: function.peers.select.active.php
                            # WHERE: info_hash, updated > stale_threshold
                            # ORDER/LIMIT: per strategy (seeders-first, random, etc.)
                            # Returns: array of peer rows
    
    peers.count.swarm.php   # SELECT COUNT seeders/leechers for one torrent
                            # Currently: function.peer.swarm.counts.php
                            # Returns: array{complete: int, incomplete: int}
                            # Used by: announce response (interval calculation)
    
    peers.count.rate.php    # SELECT COUNT announces from one IP in time window
                            # Currently: function.announce.check.rate.limit.php (inline query)
                            # Used by: rate limiting
    
    peers.scrape.php        # SELECT aggregated peer counts per torrent (for scrape)
                            # Currently: function.scrape.query.peers.php
                            # GROUP BY info_hash, returns seeders/leechers per torrent
                            # Used by: scrape.php (specific torrents)
    
    peers.scrape.all.php    # SELECT aggregated peer counts for ALL torrents
                            # Currently: function.scrape.query.all.peers.php
                            # Used by: scrape.php (full scrape)
    
    peers.clean.php         # DELETE stale peers (updated < threshold)
                            # Currently: function.task.clean.php (inline, multi-table)
                            # WHERE: updated < time - (3 * interval), OR test sentinels
                            # Used by: cron cleanup
```

### Torrent Model (torrents table)
```
src/
  model/
    torrent.upsert.php      # INSERT ... ON DUPLICATE KEY UPDATE downloads counter
                            # Currently: function.peer.completed.php
                            # Used by: announce?event=completed
    
    torrents.select.allowed.php  # SELECT all info_hashes (for closed tracker)
                                 # Currently: function.tracker.allowed.php
                                 # Returns: array of info_hash strings
                                 # Used by: announce/scrape validation
    
    torrents.select.listed.php   # SELECT listed torrents with peer counts (for index)
                                 # Currently: once.index.torrents.php (inline query)
                                 # WHERE: listed=1
                                 # LEFT JOIN peers, GROUP BY info_hash
                                 # Returns: array of torrent rows with seeders/leechers
    
    torrents.scrape.php     # SELECT torrent metadata (name, size, downloads) for scrape
                            # Currently: function.scrape.query.torrents.php
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
