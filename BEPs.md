# BEPs

A conformance audit of Phoenix against the
[BitTorrent Enhancement Proposals index (BEP 0)](https://www.bittorrent.org/beps/bep_0000.html).
The BEPs are grouped below by their status on that index, exactly as published.

Phoenix is an **HTTP tracker** — it implements the announce and scrape flows and
an optional public index. It is *not* a client, a DHT node, a peer-wire
implementation, or a `.torrent`/metainfo toolchain. The large share of the BEP
catalogue that governs those other roles is therefore out of scope by design,
and is marked accordingly as we work through it.

## Legend

- ✅ **Implemented** — Phoenix implements the tracker-facing parts as stated.
- 🟡 **Partial** — implemented, but with a gap worth noting.
- ❌ **Missing** — applies to a tracker, but not implemented.
- ➖ **N/A** — does not apply to a tracker (client / DHT / peer-wire / file-format concern, or a process/meta document).
- ⬜ **Pending** — not yet reviewed.

## Summary

All 54 indexed BEPs reviewed: **8 implemented**, **2 applicable but missing**,
**44 not applicable** (client / DHT / peer-wire / metainfo / feed concerns).

- **Implemented (tracker scope):** BEP 3 (announce), 7 (IPv6 peers), 20 (peer-id
  → client label), 23 (compact peers), 24 (return client external IP), 27
  (private/closed tracker), 31 (`retry in` on failures), 48 (HTTP scrape).
- **Applicable but not implemented:** BEP 15 (UDP tracker — excluded by the
  HTTP/PHP architecture), 8 (peer obfuscation — deferred/niche).

## Process BEPs

Final and active process BEPs (the protocol's governance and core specs).

| BEP | Title | Verdict | Notes |
| --- | --- | --- | --- |
| 0 | Index of BitTorrent Enhancement Proposals | ➖ N/A | The index document itself — this very list. Nothing to implement. |
| 1 | The BEP Process | ➖ N/A | Governance process for proposing BEPs. |
| 2 | Sample reStructured Text BEP Template | ➖ N/A | Authoring template for BEP documents. |
| 3 | The BitTorrent Protocol Specification | ✅ Implemented | Tracker HTTP announce implemented in full; metainfo + peer-wire parts are client concerns. See [BEP 3](#bep-3--the-bittorrent-protocol-specification). |
| 4 | Known Number Allocations | ➖ N/A | Registry of reserved handshake bits / extension message IDs — peer-wire concern, no tracker surface. |
| 20 | Peer ID Conventions | ✅ Implemented | `peer_id` decoded to a client label for admin stats. See [BEP 20](#bep-20--peer-id-conventions). |
| 1000 | Pending Standards Track Documents | ➖ N/A | Registry of pending docs; meta. |

## Accepted BEPs

| BEP | Title | Verdict | Notes |
| --- | --- | --- | --- |
| 5 | DHT Protocol | ➖ N/A | Distributed peer discovery run by clients/nodes — the trackerless alternative to a tracker. No tracker surface. |
| 6 | Fast Extension | ➖ N/A | Peer wire protocol extension. |
| 9 | Extension for Peers to Send Metadata Files | ➖ N/A | Peer-to-peer metadata (`ut_metadata`) exchange; client concern. |
| 10 | Extension Protocol | ➖ N/A | Peer wire extension-negotiation layer. |
| 11 | Peer Exchange (PEX) | ➖ N/A | Peer-to-peer gossip of peers; bypasses the tracker by design. |
| 12 | Multitracker Metadata Extension | ➖ N/A | `announce-list` lives in the `.torrent` and is acted on by clients. Phoenix stores a tracker list in torrent meta for magnet/`.torrent` generation, but it does not change tracker behaviour. |
| 14 | Local Service Discovery | ➖ N/A | LAN multicast peer discovery; client concern. |
| 15 | UDP Tracker Protocol | ❌ Missing | Phoenix is HTTP-only; there is no UDP listener. The HTTP scrape it does serve maps to BEP 48, not 15. See [BEP 15](#bep-15--udp-tracker-protocol). |
| 19 | HTTP/FTP Seeding (GetRight-style) | ➖ N/A | `url-list` web seeds in the `.torrent`, fetched by clients. Phoenix stores web seeds in torrent meta for generation only. |
| 23 | Tracker Returns Compact Peer Lists | ✅ Implemented | 6-byte compact IPv4 peers, request flag + tracker default. See [BEP 23](#bep-23--tracker-returns-compact-peer-lists). |
| 27 | Private Torrents | ✅ Implemented | Tracker-side closed-tracker operation (allow-list, no leakage). See [BEP 27](#bep-27--private-torrents). |
| 29 | uTorrent transport protocol | ➖ N/A | uTP peer transport (UDP); client concern. |
| 55 | Holepunch extension | ➖ N/A | Peer-assisted NAT traversal over the extension protocol; client concern. |

## Draft BEPs

| BEP | Title | Verdict | Notes |
| --- | --- | --- | --- |
| 7 | IPv6 Tracker Extension | ✅ Implemented | `peers6` compact list + IPv6 peer dicts, dual-stack. See [BEP 7](#bep-7--ipv6-tracker-extension). |
| 16 | Superseeding | ➖ N/A | Client seeding strategy; invisible to the tracker. |
| 17 | HTTP Seeding (Hoffman-style) | ➖ N/A | Web-seed variant fetched by clients. |
| 21 | Extension for Partial Seeds | ➖ N/A | Peer-protocol extension; the tracker only ever sees `left`, from which it derives seed/leech. |
| 24 | Tracker Returns External IP | ✅ Implemented | Announce echoes the client's own public address under `external ip` (gated by `announce_external_ip`). See [BEP 24](#bep-24--tracker-returns-external-ip). |
| 30 | Merkle tree torrent extension | ➖ N/A | `.torrent` metainfo / hashing; client + file format. |
| 31 | Tracker Failure Retry Extension | ✅ Implemented | Failure responses carry `retry in` (`"never"` for permanent rejections, seconds for rate-limits). See [BEP 31](#bep-31--tracker-failure-retry-extension). |
| 32 | IPv6 extension for DHT | ➖ N/A | DHT. |
| 33 | DHT scrape | ➖ N/A | DHT. |
| 34 | DNS Tracker Preferences | ➖ N/A | DNS TXT records resolved by clients; no tracker code. |
| 35 | Torrent Signing | ➖ N/A | `.torrent` metainfo signing; client + file format. |
| 36 | Torrent RSS feeds | ➖ N/A | Feed format consumed by clients. |
| 38 | Finding Local Data Via Torrent File Hints | ➖ N/A | `.torrent` metainfo hints; client. |
| 39 | Updating Torrents Via Feed URL | ➖ N/A | Metainfo / feed; client. |
| 40 | Canonical Peer Priority | ➖ N/A | Peer connection-priority computation; client. |
| 41 | UDP Tracker Protocol Extensions | ➖ N/A | Extends BEP 15 (UDP), which Phoenix does not implement. |
| 42 | DHT Security Extension | ➖ N/A | DHT. |
| 43 | Read-only DHT Nodes | ➖ N/A | DHT. |
| 44 | Storing arbitrary data in the DHT | ➖ N/A | DHT. |
| 45 | Multiple-address operation for the BitTorrent DHT | ➖ N/A | DHT. |
| 46 | Updating Torrents Via DHT Mutable Items | ➖ N/A | DHT. |
| 47 | Padding files and extended file attributes | ➖ N/A | `.torrent` metainfo / file layout; client. |
| 48 | Tracker Protocol Extension: Scrape | ✅ Implemented | The HTTP scrape Phoenix actually serves (full + specific). See [BEP 48](#bep-48--tracker-protocol-extension-scrape). |
| 49 | Distributed Torrent Feeds | ➖ N/A | Feed distribution; client. |
| 50 | Publish/Subscribe Protocol | ➖ N/A | DHT pub/sub. |
| 51 | DHT Infohash Indexing | ➖ N/A | DHT. |
| 52 | The BitTorrent Protocol Specification v2 | ➖ N/A | Metainfo / wire protocol v2. The tracker is hash-agnostic; a v2 torrent announces a 20-byte truncated infohash, so Phoenix handles it transparently. See [BEP 52](#bep-52--the-bittorrent-protocol-specification-v2). |
| 53 | Magnet URI extension - Select specific file indices for download | ➖ N/A | Magnet/client; Phoenix's magnet generator is a separate client-side utility. |
| 54 | The lt_donthave extension | ➖ N/A | Peer wire extension. |

## Deferred BEPs

| BEP | Title | Verdict | Notes |
| --- | --- | --- | --- |
| 8 | Tracker Peer Obfuscation | ❌ Missing | Peer lists are returned in the clear (compact + dict). No obfuscation layer — a deferred, rarely-implemented spec. See [BEP 8](#bep-8--tracker-peer-obfuscation). |
| 18 | Search Engine Specification | ➖ N/A | A torrent-search-engine spec, not a tracker feature. Phoenix's public index is unrelated. |
| 22 | BitTorrent Local Tracker Discovery Protocol | ➖ N/A | Clients discovering local trackers; network/client concern. |
| 26 | Zeroconf Peer Advertising and Discovery | ➖ N/A | LAN peer advertising; client concern. |
| 28 | Tracker exchange | ➖ N/A | Peers gossiping tracker URLs (`lt_tex`); peer-protocol concern. |

## Withdrawn BEPs

None — the index lists no withdrawn BEPs at this time.

## Rejected BEPs

None — the index lists no rejected BEPs at this time.

## Detailed assessments

### BEP 3 — The BitTorrent Protocol Specification

**Verdict: ✅ Implemented (tracker scope).**

BEP 3 spans three areas; only the tracker HTTP protocol is a tracker's job:

- **Metainfo (`.torrent`) structure** — client concern. Phoenix never parses
  `.torrent` files server-side; the optional in-browser helpers (`magnet.php`,
  `assets/add.js`) read them client-side. N/A to the tracker.
- **Peer wire protocol** — peer concern. N/A.
- **Tracker HTTP protocol** — implemented:
  - GET announce params (`info_hash`, `peer_id`, `port`, `uploaded`,
    `downloaded`, `left`, `event`, `compact`, `no_peer_id`, `numwant`) parsed in
    `sanitize_tracker_params()` and `peer_parse_announce_optional()`
    (`src/functions/`).
  - Bencoded response with `interval`, `min interval`, `complete`,
    `incomplete`, `peers` — `view_announce_bencode()`
    (`src/views/bencode.announce.php`), emitted through the single
    `bencode_encode()` which owns length prefixes and BEP 3 key ordering.
  - Events `started` / `stopped` / `completed` plus the regular keepalive are
    dispatched in `announce_controller()` (`src/controller/announce.php`):
    `completed` increments `downloads`, `stopped` deletes the peer and returns
    an empty body.
  - `failure reason` on validation errors via `tracker_error()`.

Notes / minor gaps:

- `started` is not special-cased; it falls through to the normal new/changed
  peer insert. That is BEP-3-conformant (the event is advisory) — flagging only
  so it's a conscious choice.
- Optional `tracker id` and `warning message` keys are not emitted. Both are
  optional in BEP 3; omitting them is fine.

### BEP 20 — Peer ID Conventions

**Verdict: ✅ Implemented (stats only).**

`stats_client_detect()` (`src/functions/stats.client.detect.php`) decodes a
`peer_id` into a client label using both BEP 20 conventions — Azureus-style
`-XX####-` and Shadow's-style single-letter prefixes — feeding the admin Peers
view via the stats hooks. The `peer_id` is decoded transiently and never stored.
Coverage is a curated subset of clients; unrecognised codes fall back to the raw
two-letter code or `Unknown`, which is all a tracker needs. Everywhere else the
tracker treats `peer_id` as opaque (validated as 40 hex chars, stored, and
echoed in non-compact peer lists).

### BEP 15 — UDP Tracker Protocol

**Verdict: ❌ Missing (HTTP-only by design).**

BEP 15 defines a binary tracker protocol over **UDP** (connect → announce →
scrape datagrams). Phoenix has no UDP listener — `grep` finds no socket server
anywhere in `src/`, `bin/`, or `public/`, and the per-request PHP/FPM model
can't hold the long-lived socket a UDP tracker needs. So BEP 15 is not
implemented, and realistically won't be without a separate daemon.

This applies to a tracker, so it's a genuine gap rather than N/A — but a
deliberate architectural one.

**Not the same as HTTP scrape:** BEP 15 is the *UDP* tracker protocol, which is
easy to confuse with Phoenix's scrape endpoints. Those endpoints implement
**HTTP scrape** — the convention formalised by **BEP 48** — a separate spec from
BEP 15.

### BEP 23 — Tracker Returns Compact Peer Lists

**Verdict: ✅ Implemented.**

`peers_format_compact()` (`src/functions/peers.format.compact.php`) emits the
6-bytes-per-peer compact IPv4 form (4-byte address + 2-byte port), concatenated
into the `peers` byte string by `view_announce_bencode()`. Mode selection
(`peer_parse_announce_optional()`): an explicit `compact=` request flag wins,
otherwise the `default_compact` tracker setting decides. The IPv6 companion
(`peers6`, 18 bytes/peer) is BEP 7 and is emitted alongside in the same path.
Non-compact responses also drop `peer_id` when the client sends `no_peer_id`.

One spec nuance: BEP 23 lets a tracker that *requires* compact omit the
non-compact path entirely. Phoenix always honours both, which is conformant —
just noting it offers more than the minimum.

### BEP 27 — Private Torrents

**Verdict: ✅ Implemented (tracker side).**

BEP 27 has two halves. The `private` flag in the `.torrent` and the resulting
suppression of DHT / PEX / LSD are **client** responsibilities — N/A to a
tracker. The tracker-side half is operating as a *private (closed) tracker*,
which Phoenix supports:

- With `open_tracker` off, announces are rejected unless the `info_hash` is in
  the allowed list (`announce_controller()`, `src/controller/announce.php`).
- Scrape filters requested hashes through `tracker_filter_info_hashes()` and
  errors out if none are allowed, so a closed tracker never confirms torrents
  the caller isn't entitled to (`public/scrape.php`).
- Full scrape is separately gated behind `full_scrape`, and the Settings page
  warns that enabling it on a closed tracker exposes every tracked `info_hash`.

No leakage path was found between closed-mode announce and scrape.

### BEP 7 — IPv6 Tracker Extension

**Verdict: ✅ Implemented.**

Phoenix is dual-stack. `parse_ipv6()` and the address-resolution helpers
(`peer.resolve.addresses.php`, `peer.address.candidates.php`) capture an IPv6
address + port alongside IPv4, stored per peer. On announce:

- Compact mode emits a separate `peers6` byte string at 18 bytes/peer (16-byte
  address + 2-byte port), built by `peers_format_compact()` next to the IPv4
  `peers` string.
- Non-compact mode emits one dict per peer; `peer_format_dict()` carries `ip`
  (v4 preferred when a peer has both) and `port`.

A peer can therefore announce and be returned over either family.

### BEP 24 — Tracker Returns External IP

**Verdict: ✅ Implemented.**

The announce response echoes the requester's own public address back under an
`external ip` key (raw 4-byte IPv4 or 16-byte IPv6, packed by `inet_pton()`),
so a NATed client can learn how the tracker sees it. `peer_external_ip()`
(`src/functions/peer.external.ip.php`) picks the address — preferring the family
the request arrived on (per `REMOTE_ADDR`) when the peer resolved on both —
from the already-resolved `$peer['ipv4']`/`$peer['ipv6']`, and
`view_announce_bencode()` emits the packed key. The `?xml`/`?json` debug views
carry the same value as a human-readable string. Gated by the
`announce_external_ip` setting (default on). Implemented in #68.

Not to be confused with the existing `external_ip` setting, which governs the
*opposite* direction — whether the tracker accepts a client-declared address as
an input candidate.

### BEP 31 — Tracker Failure Retry Extension

**Verdict: ✅ Implemented.**

Failure responses can now carry BEP 31's `retry in` key — an integer number of
seconds, or the string `"never"`. `tracker_error()`
(`src/functions/tracker.error.php`) takes an optional `$retry_in` and threads it
into all three error views; `view_error_bencode()` emits it as a bencode integer
or byte string (sorted after `failure reason`), with the `?xml`/`?json` debug
views mirroring it. Call sites are classified:

- **`"never"`** — permanent rejections that a retry can't fix: `Info Hash is
  invalid.`, `Peer ID is invalid.`, `Torrent is not allowed.` (announce and
  scrape), `Tracker scraping is not allowed.`.
- **Seconds** — the rate-limit rejection (`announce.check.rate.limit.php`) hints
  `min_interval / 5`, the window after which the limit clears.

Ambiguous failures (scrape/DB errors) deliberately omit the key. Implemented
in #69.

### BEP 48 — Tracker Protocol Extension: Scrape

**Verdict: ✅ Implemented.**

This is the HTTP scrape convention — the protocol Phoenix's scrape endpoint
actually speaks (older comments mislabelled it "BEP 15"; now corrected — see
that entry).
`public/scrape.php` routes to three controllers:

- **Specific** (`scrape_specific_controller`): one or more `info_hash` params →
  a `files` dict keyed by raw infohash, each with `complete` / `downloaded` /
  `incomplete`. Requested-but-unknown hashes are pre-seeded with zero counts so
  they still appear. Closed trackers filter to allowed hashes first.
- **Full** (`scrape_full_controller`): no `info_hash`, gated behind
  `full_scrape` — every tracked torrent.
- The `info_hash`-derived scrape URL convention (swap `announce` → `scrape`) is
  the client's job; Phoenix simply serves `scrape.php`.

`view_scrape_bencode()` casts the possibly-empty `files` value to an object so it
encodes as a bencode dict (`de`), not a list — matching the BEP.

### BEP 52 — The BitTorrent Protocol Specification v2

**Verdict: ➖ N/A (transparently compatible).**

v2 is a metainfo + peer-wire revision (SHA-256 merkle trees, new `.torrent`
layout) — all client/file-format concerns. The only tracker-visible surface is
the infohash: a v2 torrent announces a **20-byte truncated** SHA-256 infohash,
and a hybrid torrent announces both its v1 and truncated-v2 infohashes as
separate swarms. Because Phoenix treats `info_hash` as an opaque 40-hex-char
(20-byte) token end to end, it tracks v2 and hybrid swarms with no changes. No
v2-specific tracker work is required.

### BEP 8 — Tracker Peer Obfuscation

**Verdict: ❌ Missing.**

BEP 8 proposes returning peer addresses in an obfuscated form so a passive
observer of tracker traffic can't trivially harvest the swarm. Phoenix returns
peers in the clear — compact (`peers`/`peers6`) or plain dicts. There is no
obfuscation layer, and it's a deferred spec that few trackers or clients ever
adopted, so "missing" here is unremarkable. Flagged only for completeness as a
tracker-facing proposal Phoenix does not implement.

## Optional items within the implemented BEPs

Beyond the whole-BEP verdicts above, each tracker BEP carries *optional*
keys/parameters. Phoenix implements essentially all the ones with a real
consumer; this tracks both sides so the gaps are deliberate, not forgotten.

### Implemented (optional)

| Item | BEP | Notes |
| --- | --- | --- |
| `min interval` | 3 | `min_interval` setting (600s default) |
| `complete` / `incomplete` | 3 | swarm counts in the announce reply |
| Compact peer lists | 23 | `compact=` request flag + `default_compact` |
| `peers6` / IPv6 peers | 7 | dual-stack |
| Honour `no_peer_id` | 3 | omits `peer id` in non-compact replies |
| Honour `numwant` | — (unofficial spec, not a BEP) | clamped to `[0, max_peers]`; `numwant=0` returns no peers |
| Consume client `ip=` | 3 | gated by `external_ip` |
| `external ip` (returned) | 24 | gated by `announce_external_ip` |
| `retry in` on errors | 31 | `"never"` or seconds |
| scrape `min_request_interval` | 48 | `scrape_min_interval` setting (1800s default) |

### Not implemented (optional)

| Item | BEP | What it's for | Worth doing? |
| --- | --- | --- | --- |
| `warning message` | 3 | Non-fatal notice shown to the user while the reply is still processed (e.g. maintenance soon, deprecated client). | Maybe — the only one with real operator value; a setting plus one response key. |
| `tracker id` | 3 | Tracker-assigned session id the client echoes back on later announces. | No — Phoenix is stateless and keys peers by `(info_hash, peer_id)`; nothing would consume it. |
| scrape per-torrent `name` | 48 | Optional torrent name inside each scrape `files` entry. | No — deliberately omitted; it bloats scrape and strict clients can reject extra keys (the bencode scrape is pinned to exactly `complete`/`downloaded`/`incomplete`). |
| `key` request param | 3 | Client identity hint to follow a peer across IP changes. | No — redundant; the `peer_id` is stable across IP changes, so the row updates regardless. |
| `supportcrypto` / crypto flags | — (MSE, not a BEP) | Encryption-capability hint; trackers may relay `crypto_flags` in compact peers. | No — niche, largely obsolete, not a tracker BEP. |
| `corrupt` / `redundant` request stats | 3 | Informational counters some clients send. | No — purely informational. |
