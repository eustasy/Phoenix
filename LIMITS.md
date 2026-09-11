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
| Admin dashboard latency | `COUNT(*)` over the ledger | **~0.24 s per million rows** |

The tracker hot path is **CPU-bound, not database-bound**: the whole announce
costs 6.4 ms, of which under 1 ms is querying. Everything else is bound by
`memory_limit` on an endpoint that builds a whole response in an array.

## The reference box

| | |
| --- | --- |
| CPU | 1 vCPU (Intel Xeon E5 v4, 2.5 Ghz, 4 MB) |
| RAM | 1 GB + 2 GB Swap |
| PHP | 8.5 FPM, `memory_limit=128M`, `max_execution_time=30`, `ext-maxminddb` |
| Database | MariaDB 11.8, local socket, `innodb_buffer_pool_size=128M`, `innodb_flush_log_at_trx_commit=1` |
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

```
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

| Query | Rows | Time |
| --- | --- | --- |
| `COUNT(*)` events | 1,252,956 | **303.69 ms** |
| `COUNT(*)` peers | 383 | 0.23 ms |
| `COUNT(*)` torrents | 48 | 0.11 ms |
| `GROUP BY country`, `event='completed'` (uses `geo`) | 1,252,956 | 667.23 ms |
| `SUM` over an unindexed column | 1,252,956 | 1,595.92 ms |

That is **~0.24 s per million rows** counting, **~1.3 s per million** for an
aggregate that cannot use an index.

Phoenix does the unqualified count in exactly two places — `torrents_count()`
(dashboard, sidebar badge, paged Torrents and Traffic listings) and
`peers_count()` (sidebar badge, paged Peers listing). **Neither is on the
announce or scrape path**; the cost lands on admin page loads. If it becomes a
problem, cache the count or read an approximate one from
`information_schema.TABLES`.

Note that `TABLE_ROWS` in `information_schema` is itself an estimate under
InnoDB — the ledger reports 1,252,956 against an exact 1,261,343.

### `OPTIMIZE TABLE` is a full rebuild

`db_optimize()` issues `CHECK`, `ANALYZE`, `REPAIR`, `OPTIMIZE` for `peers`,
`tasks`, `task_runs` and `torrents`. Of those:

- `OPTIMIZE TABLE` maps to `ALTER TABLE … FORCE`, a full rebuild. It needs
  **free disk equal to the table's size** and is mostly, not entirely, online.
- `CHECK TABLE` is a full scan.
- `REPAIR TABLE` is **not supported** and returns a note — a no-op.

For scale: rebuilding the 1.25M-row, 154 MB events table took **22.6 s** on this
box. The other four tables took under 0.05 s each.

Leave `clean_with_cron` on so this runs on a schedule rather than on a request,
and give the disk headroom for the largest table.

### Disk and the buffer pool

Storage is roughly **2.1×** the row data: the events ledger measured 154.1 MB
before conversion and 328.9 MB after, same rows.

Pages are cached in `innodb_buffer_pool_size`, 128 MB by default. On this box
the Phoenix tables total 329 MB, so **the working set does not fit** and the
ledger aggregates above are reading from disk. That is why they cost hundreds of
milliseconds while the announce queries — which touch only the clustered primary
key for one `info_hash`, and stay resident — cost fractions of one.

The peers table is the part worth keeping resident. At ~250 bytes/row, 280,000
peers is around 70 MB, so the default pool covers the announce ceiling even
though it cannot cover the ledger. Raise it if you carry both a large swarm and
a large ledger.

## Where it breaks first

In the order you will actually hit them:

1. **`public_index` memory**, at ~20,500 torrents with meta on. Turn off
   `index_show_meta` for 3× the headroom, raise `memory_limit`, or turn the
   index off — it is off by default.
2. **`full_scrape` memory**, at ~54,500 torrents. Turn it off; clients fall back
   to per-hash scrapes, which are indexed and cheap.
3. **Admin `COUNT(*)`**, once the ledger passes a few million rows. Set
   `stats_retention` so it is pruned.
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
| Aggregate query time | Row count linearly, buffer-pool residency sharply |
| GeoIP throughput | `ext-maxminddb` present or not — a ~40× step, not a slope |
| Disk | Row count × ~2.1 |

Two of these are steps rather than slopes, and both are worth checking before
tuning anything else: whether `ext-maxminddb` is loaded, and whether the working
set fits the buffer pool.
