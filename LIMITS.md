# Limits

How far a Phoenix install goes before something gives, which thing gives first,
and what to do about it.

Every number here was measured on the reference box described below, running
InnoDB, on 2026-09-11. Figures labelled **derived** are arithmetic on a measured
figure rather than a separate measurement. Treat all of it as a starting point
with the working shown, not as a guarantee — [Scaling these
numbers](#scaling-these-numbers) says which move with what.

## At a glance

| Ceiling | Where it binds | Reference box |
| --- | --- | --- |
| Concurrent peers | Announce CPU | **~280,000** saturated, **~140,000** at 50% headroom |
| Torrents, public index on | `index.php` memory | **~20,500** with meta, **~61,000** without |
| Torrents, full scrape on | `scrape.php` memory | **~54,500** |
| Torrents, full scrape off | Nothing in Phoenix | Disk and admin-page latency |
| Admin dashboard latency | `COUNT(*)` over the ledger | **~0.22 s per million rows**, once the pool holds it |

The tracker hot path is **CPU-bound, not database-bound**: the whole announce
costs 6.4 ms, of which under 1 ms is querying. Everything else is bound by
`memory_limit` on an endpoint that builds a whole response in an array.

## The reference box

| | |
| --- | --- |
| CPU | 1 vCPU (Intel Xeon E5 v4, 2.5 Ghz, 4 MB) |
| RAM | 1 GB + 2 GB Swap |
| PHP | 8.5 FPM, `memory_limit=128M`, `max_execution_time=30`, `ext-maxminddb` |
| Database | MariaDB 11.8, local socket, `innodb_buffer_pool_size=512M`, `innodb_flush_log_at_trx_commit=1` |
| Tables | 1.25M events, 48 torrents, 383 peers |
| Settings | `announce_rec_interval=1800`, closed tracker, `stats_enabled`, `public_index`, `full_scrape` |

A deliberately small box — the floor, not the target.

## Announce throughput

Measured by calling `announce_controller()` 80 times against a seeded 134-peer
swarm, each announce from a distinct source IP so the rate limiter measures the
real path rather than its own rejection:

| | |
| --- | --- |
| `announce_controller()` median | **3.78 ms** |
| p90 | 5.54 ms |
| best | 2.70 ms |

Per-request PHP-FPM overhead — bootstrap, config load, DB connect — measured
separately over HTTPS on a reused connection as the gap between a rejected
announce (2.98 ms) and a static asset (0.39 ms): **~2.6 ms**.

```text
announce      = 3.78 ms controller + 2.6 ms request overhead
              = 6.4 ms                                          (derived)
announces/sec = 1000 / 6.4  = ~156 per core
peers         = 156 × 1800  = ~280,000                          (derived)
```

**~280,000 concurrent peers** with the core pinned, **~140,000** keeping half
the core for cleanup, the admin panel and the OS. Doubling
`announce_rec_interval` doubles both.

Neither figure is sensitive to how many torrents the tracker carries. The
permission check is one indexed `IN` against the clustered primary key, asked
only about the hashes the request names.

### Where the announce goes

| Step | Cost |
| --- | --- |
| Peer upsert (one row, durable commit) | 3.11 ms |
| `peers_count_swarm`, 134-peer swarm | 0.46 ms |
| `peers_select_active`, 134-peer swarm | 0.29 ms |
| `torrents_filter_allowed`, one hash | 0.18 ms |
| GeoIP lookup | 0.008 ms |
| `bencode_encode`, 50-peer reply | 0.002 ms |

**The single peer write is the announce.** At `innodb_flush_log_at_trx_commit=1`
every commit waits on an fsync, and that one row costs more than every read,
the geo lookup and the encode put together. Its best case is 0.947 ms, so most
of the 3.11 ms is the disk, not the query.

This is the number to attack if announces ever become the constraint, and it is
a database tuning question rather than a Phoenix one — `=2` trades a commit's
durability window for the fsync, and faster storage helps directly. Both are
server-wide settings, so neither is Phoenix's to set.

### GeoIP

Measured over 50,000 lookups against `/var/lib/GeoIP/GeoLite2-Country.mmdb`,
resolved by auto-discovery (`stats_geo_database` empty):

| | |
| --- | --- |
| Per lookup | **7.54 µs** |
| Throughput | **132,695/sec** |

That is 0.1% of an announce, so with `ext-maxminddb` loaded geo enrichment is
effectively free. Without the extension the same traversal runs in pure PHP and
costs roughly 40× more. The admin **Support** page reports which reader is live.

## Memory ceilings

Three endpoints build an entire response in memory. Measured by rendering 20,000
synthetic torrents through each view and reading `memory_get_peak_usage()`:

| Endpoint | Per torrent | Ceiling at 128M |
| --- | --- | --- |
| Public index, `index_show_meta` on | 4,552 B view + 1,573 B row | **~20,500** |
| Public index, meta off (JSON index shape) | 487 B view + 1,573 B row | **~61,000** |
| Full scrape | 734 B view + 1,573 B row | **~54,500** |

Ceilings allow 8 MB for PHP itself and scale linearly with `memory_limit` — at
`256M` every figure roughly doubles.

`index_show_meta` is the most expensive switch on the box: it costs 9× the view
memory per torrent, because each torrent gains a second full-width row carrying
its filename, file list, trackers and webseeds. The full-scrape row figure is
conservative — a scrape reads counts, not meta, so its real ceiling is higher.

`max_execution_time=30` binds on none of them.

**Turning `full_scrape` off removes the only unauthenticated request that costs
`O(all torrents)`.** With it and `public_index` off, nothing in Phoenix reads
the whole torrents table on a public request, and the torrent ceiling becomes
disk and admin-page latency rather than a hard limit.

Admin listings are paged (`admin_torrents_limit=100`, `admin_peers_limit=200`,
`admin_traffic_limit=100`), so they do not grow with the table.

## Database behaviour worth knowing

### `COUNT(*)` costs an index scan

There is no stored row counter, so an unqualified `SELECT COUNT(*)` scans an
index. Measured against the 1.25M-row events ledger:

| Query | Rows | Cold | Warm |
| --- | --- | --- | --- |
| `COUNT(*)` events | 1,252,956 | 353 ms | **279 ms** |
| `COUNT(*)` peers | 383 | — | 0.23 ms |
| `COUNT(*)` torrents | 48 | — | 0.11 ms |
| `GROUP BY country`, `event='completed'` (uses `geo`) | 1,252,956 | 805 ms | **522 ms** |
| `SUM` over an unindexed column | 1,252,956 | 751 ms | **427 ms** |

Warm means the pages are already in the buffer pool, which on this box they now
stay — see [below](#disk-and-the-buffer-pool). That is **~0.22 s per million
rows** counting, **~0.34 s per million** for an aggregate that cannot use an
index.

Phoenix does the unqualified count in exactly two places — `torrents_count()`
(dashboard, sidebar badge, paged Torrents and Traffic listings) and
`peers_count()` (sidebar badge, paged Peers listing). **Neither is on the
announce or scrape path**; the cost lands on admin page loads. If it becomes a
problem, cache the count or read an approximate one from
`information_schema.TABLES`.

Note that `TABLE_ROWS` in `information_schema` is itself an estimate under
InnoDB — the ledger reports 1,252,956 against an exact 1,261,343.

### `OPTIMIZE TABLE` is a full rebuild

On InnoDB `OPTIMIZE TABLE` maps to `ALTER TABLE … FORCE`: a full rebuild that
repacks sparsely-filled pages and re-analyses. It needs **free disk equal to the
table's size**, is mostly but not entirely online, and reports a routine `note:
Table does not support optimize, doing recreate + analyze instead` before its
`status: OK`.

It is also the only statement that reclaims anything. Measured on a
peers-shaped table:

| Step | Data on disk | Free | Time |
| --- | --- | --- | --- |
| 120,000 rows | 25.7 MB | 7.0 MB | |
| deleted all but 1,200 | 25.7 MB | 7.0 MB | |
| after `ANALYZE` | 25.7 MB | 7.0 MB | 1.6 ms |
| after `OPTIMIZE` | **0.2 MB** | 0.0 MB | 37.5 ms |

`ANALYZE` updates index statistics and frees nothing; `OPTIMIZE` shrank the
table by 99%. They are not interchangeable, which is why Phoenix runs them on
separate schedules: `bin/clean-and-optimize.php` analyses often, and
`bin/optimize-database.php` rebuilds daily. On a table in steady state a rebuild
reclaims nothing anyway — InnoDB reuses the pages its own deletes freed — so it
earns its cost only after a bulk deletion.

`DATA_FREE` is a poor trigger for deciding when to rebuild: above it read
7.0 MB while the real waste was 25.5 MB, because it counts fully-free extents
rather than half-empty pages.

Two statements are deliberately absent from the scheduled runs:

- **`REPAIR TABLE`**, which on MariaDB reports `status: OK` on an InnoDB table
  and performs a **second full rebuild** — verified by watching
  `information_schema.TABLES.CREATE_TIME` change across it. It is not the no-op
  MySQL's "doesn't support repair" note suggests, and including it rebuilt every
  table twice per run for no benefit.
- **`CHECK TABLE`**, a full scan of every row and index. InnoDB verifies page
  checksums as it reads, so corruption surfaces during normal use; it is a
  Utilities action rather than a schedule.

For scale: rebuilding the 1.25M-row, 154 MB events table took **22.6 s** on this
box. The other tables took under 0.05 s each — and `events` is excluded from the
rebuild entirely, being the largest and the most append-mostly. Prune it with
`stats_retention` and optimize it by hand afterwards.

**Failures used to be invisible here.** These statements report problems as rows
inside their result sets — `Msg_type: Error`, then `status: Operation failed` —
while `mysqli_errno()` stays `0` and `mysqli_multi_query()` still returns true.
`db_maintenance()` now walks every result set and fails the run on any `Error`
row, so a rebuild that dies partway (a full disk) no longer logs a success.

### Disk and the buffer pool

Storage is roughly **2.1×** the row data: the events ledger measured 154.1 MB
before conversion and 328.9 MB after, same rows. Sizes by index, all of it the
ledger — everything else on this box rounds to 0.1 MB:

| Index | Size |
| --- | --- |
| `events.PRIMARY` (the clustered rows) | 147.7 MB |
| `events.geo` | 85.9 MB |
| `events.info_hash` | 70.8 MB |
| `events.time` | 24.5 MB |
| **Total InnoDB** | **329.1 MB** |

Pages are cached in `innodb_buffer_pool_size`, **128 MB by default — which this
dataset does not fit**. The failure mode is worth recognising, because it is not
a gentle slope: at 128 MB the two cheap aggregates (24.5 MB + 85.9 MB) very
nearly fit, but any query touching the clustered index pulls 147.7 MB through
the pool and evicts everything. Repeat runs then measured a **~100% miss rate** —
every pass re-read from disk, and a warm cache never formed.

Raising the pool past the dataset fixes it outright: misses go to **0% from the
second pass on**, and the aggregates above drop by a third. Sizing is just the
data plus InnoDB's own overhead — roughly 10% for control blocks and the
adaptive hash index, so **~360 MB** is the real requirement here and 384 MB is
the round number. This box runs 512 MB, which measured 250 MB of resident pages
against 257 MB free: comfortably more than needed.

Two other caches ship at 128 MB by default and are worth checking, because on an
all-InnoDB install both are nearly pure waste:

- **`key_buffer_size`** is the MyISAM key cache. With no MyISAM tables it does
  nothing at all — verified here by watching every `Key_%` counter sit at a zero
  delta over 60 seconds. It cannot be set to 0 (`Cannot drop default keycache`),
  so 8M is the floor.
- **`aria_pagecache_buffer_size`** backs the system tables and on-disk internal
  temp tables, which totalled 5.5 MB here against its 128 MB default.

Trimming those two freed ~216 MB, which is where this box's larger InnoDB pool
came from — no extra RAM. `innodb_buffer_pool_size` and `key_buffer_size` are
both dynamic; `aria_pagecache_buffer_size` needs a restart.

None of this speeds up the tracker. Announce queries touch one `info_hash` in
the clustered index, stay resident under any pool size, and measured fractions
of a millisecond. This is admin page latency only.

## Where it breaks first

In the order you will actually hit them:

1. **`public_index` memory**, at ~20,500 torrents with meta on. Turn off
   `index_show_meta` for 3× the headroom, raise `memory_limit`, or turn the
   index off — it is off by default.
2. **`full_scrape` memory**, at ~54,500 torrents. Turn it off; clients fall back
   to per-hash scrapes, which are indexed and cheap.
3. **Admin `COUNT(*)`**, once the ledger outgrows the buffer pool — the cliff
   described above, not a gradual slope. Set `stats_retention` so the ledger is
   pruned, or size the pool past the dataset.
4. **Announce CPU**, at ~140,000 peers per core with headroom. Add cores, or
   raise `announce_rec_interval`.
5. **Peer cleanup.** `peers_clean()` deletes on `updated < threshold` and there
   is no index on `updated`, so it is a full scan, and a large delete also holds
   row locks and builds undo. It runs from cron when `clean_with_cron` is set,
   and from a request otherwise. Set it.

## Scaling these numbers

| Number | Scales with |
| --- | --- |
| Announce rate, peer ceiling | CPU cores and clock, linearly |
| Peer write cost | Disk fsync latency, and `innodb_flush_log_at_trx_commit` |
| Memory ceilings | `memory_limit`, linearly; and `index_show_meta`, ~9× |
| Aggregate query time | Row count linearly; buffer-pool residency as a cliff, not a slope |
| GeoIP throughput | `ext-maxminddb` present or not — a ~40× step, not a slope |
| Disk | Row count × ~2.1 |

Two of these are steps rather than slopes, and both are worth checking before
tuning anything else: whether `ext-maxminddb` is loaded, and whether the working
set fits the buffer pool.
