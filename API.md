# Phoenix HTTP API

Every HTTP surface Phoenix exposes, and what it returns. Three groups:

- **[Tracker protocol](#tracker-protocol)** — `announce` and `scrape`, spoken by
  torrent clients. Bencode by default, with JSON and XML alternatives.
- **[Public read endpoints](#public-read-endpoints)** — the torrent index and
  tracker stats. No authentication; off unless enabled.
- **[Management API](#management-api)** — `/api/`, for adding and editing
  torrents. Authenticated.

| Path | Method | Auth | Formats | Does |
| --- | --- | --- | --- | --- |
| [`/announce`](#get-announce) | GET | none | bencode, `?json`, `?xml` | Register a peer, get others in the swarm. |
| [`/scrape`](#get-scrape) | GET | none | bencode, `?json`, `?xml` | Swarm counts, one or many torrents. |
| [`/scrape?stats`](#get-scrapestats) | GET | none | HTML, `?json`, `?xml` | Tracker-wide totals. |
| [`/`](#get--torrent-index) | GET | none | HTML, `?json`, `?xml` | The public torrent index. |
| [`/api`](#get-api) | GET | none | JSON, `?xml` | Version probe. |
| [`/api/torrents`](#get-apitorrents) | GET | key or session | JSON, `?xml` | List torrents with swarm stats. |
| [`/api/torrent/add`](#post-apitorrentadd) | POST | key or session + CSRF | JSON, `?xml` | Add a torrent, or upload a `.torrent`. |
| [`/api/torrent/update`](#post-apitorrentupdate) | POST | key or session + CSRF | JSON, `?xml` | Edit a torrent's fields. |
| [`/api/torrent/list`](#post-apitorrentlist-and-apitorrentdelist) | POST | key or session + CSRF | JSON, `?xml` | Show on the public index. |
| [`/api/torrent/delist`](#post-apitorrentlist-and-apitorrentdelist) | POST | key or session + CSRF | JSON, `?xml` | Hide from the public index. |
| [`/api/torrent/delete`](#post-apitorrentdelete) | POST | key or session + CSRF | JSON, `?xml` | Delete a torrent and its peers. Gated off by default. |

"Key or session" is an `Authorization: Bearer` key **or** a logged-in
`admin.php` session; CSRF applies only to the session form, since a bearer key
cannot be forged cross-site. See [Authentication](#authentication).

Three endpoints are off unless enabled: the public index needs `public_index`,
a full `/scrape` needs `full_scrape`, and `/api/torrent/delete` needs
`api_allow_delete` for anyone but the admin.

Two browser surfaces are out of scope because they are pages, not APIs:
`admin.php` (the admin panel and first-run installer) and `magnet.php` (a
client-side magnet generator that talks to nothing).

For configuration see [README.md](./README.md); for how requests flow through
the code see [.claude/docs/architecture.md](./.claude/docs/architecture.md).

## Table of Contents

- [Conventions](#conventions)
  - [Response formats](#response-formats)
  - [Errors](#errors)
  - [CORS](#cors)
- [Tracker protocol](#tracker-protocol)
  - [GET /announce](#get-announce)
  - [GET /scrape](#get-scrape)
- [Public read endpoints](#public-read-endpoints)
  - [GET / (torrent index)](#get--torrent-index)
  - [GET /scrape?stats](#get-scrapestats)
- [Management API](#management-api)
  - [Authentication](#authentication)
  - [GET /api](#get-api)
  - [GET /api/torrents](#get-apitorrents)
  - [POST /api/torrent/add](#post-apitorrentadd)
  - [POST /api/torrent/update](#post-apitorrentupdate)
  - [POST /api/torrent/list and /api/torrent/delist](#post-apitorrentlist-and-apitorrentdelist)
  - [POST /api/torrent/delete](#post-apitorrentdelete)
  - [Torrent meta fields](#torrent-meta-fields)

## Conventions

### Response formats

Every endpoint below answers in one of three formats, chosen by a query flag.
The flag is a bare presence check — `?json` and `?json=1` are the same, and
`?json=0` still selects JSON.

| Flag | Format | Content-Type |
| --- | --- | --- |
| _(none)_ | Bencode on the tracker protocol, HTML on public pages | `text/plain; charset=ISO-8859-1` / `text/html; charset=UTF-8` |
| `?json` | JSON | `application/json; charset=UTF-8` |
| `?xml` | XML | `application/xml; charset=UTF-8` |

`?xml` wins when both are given. The management API is the exception: it is
JSON by default and has no bencode form.

The default `ISO-8859-1` charset on tracker responses is deliberate — announce
and scrape carry binary peer data, and declaring UTF-8 would corrupt it.

### Errors

Errors are returned with **HTTP 200** and an error body. That is the
BitTorrent convention: BEP 3 defines failure as a `failure reason` key in the
bencode response, not a status code, and clients look for it there.

| Format | Body |
| --- | --- |
| Bencode | `d14:failure reason21:Info Hash is invalid.e` |
| JSON | `{"error":"Info Hash is invalid."}` |
| XML | `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><error>Info Hash is invalid.</error>` |

Some errors carry BEP 31's retry hint as `retry_in` — seconds, or the string
`never` for a permanent refusal (an invalid info_hash, a torrent not allowed on
a closed tracker).

### CORS

`announce`, `scrape` and the public index send `Access-Control-Allow-Origin: *`
so browser-based clients can reach them. The management API and the admin panel
deliberately send no CORS header.

## Tracker protocol

### GET /announce

BEP 3 announce, with BEP 7 (IPv6), BEP 23 (compact peers) and BEP 24
(external IP). Registers or updates the calling peer and returns others in the
swarm.

**Required:**

| Parameter | Notes |
| --- | --- |
| `info_hash` | The torrent. 20 raw bytes URL-encoded, or 40 hex chars. Repeatable; the first valid one is used. |
| `peer_id` | The client's ID, same encoding as `info_hash`. |
| `port` | Listening port, 1–65535. |

**Optional:**

| Parameter | Default | Notes |
| --- | --- | --- |
| `left` | absent → unknown | Bytes remaining. `0` marks the peer a seeder. |
| `uploaded` / `downloaded` | `0` | Cumulative byte counters, client-reported. |
| `event` | — | `started`, `stopped`, `completed`. Anything else is a keepalive. |
| `numwant` | `default_peers` | Peers wanted, clamped to `[0, max_peers]`. `0` is valid and means none. |
| `compact` | `default_compact` | `1` returns BEP 23 compact peers. |
| `no_peer_id` | `0` | `1` omits `peer_id` from a non-compact response. |
| `ip` / `ipv4` / `ipv6` | ignored | Client-declared address. Honoured **only** when `allow_client_ip` is on, and always lowest priority. Accepts `ip:port`. |

`event=stopped` returns an **empty body** — the client expects no response.

The address Phoenix registers comes from the connection (`REMOTE_ADDR`), or a
forwarded header when you have configured `forwarded_headers` and
`trusted_proxies`. See [README.md](./README.md#reverse-proxies--client-ip-address);
misconfiguring it either hides every peer behind your proxy or lets clients
spoof each other.

A family (v4 or v6) counts only when it has **both** an address and a port.
One family failing does not fail the announce — the other still registers.

**`?json` response:**

```json
{
  "complete": 108,
  "incomplete": 2,
  "interval": 1800,
  "min_interval": 60,
  "peers": [
    { "peer_id": "2d7142353233302d…", "ip": "23.234.100.83", "port": 55249 }
  ],
  "external_ip": "81.78.119.233"
}
```

`peer_id` is hex-encoded. Each peer carries one address: IPv4 when it has one,
otherwise IPv6. `external_ip` is BEP 24 — the tracker's view of your own
address — and is present only when `announce_external_ip` is on.

`?xml` gives the same data under an `<announce>` root, with `<peer>` children.

Announce rate is limited per peer by `announce_rate_limit` and
`announce_rate_window`; exceeding it returns an error with a `retry_in`.

### GET /scrape

BEP 48 scrape. Swarm counts without joining the swarm.

| Parameter | Notes |
| --- | --- |
| `info_hash` | The torrent. 40 hex chars, or 20 raw bytes URL-encoded. **Repeatable** — see below. Omit entirely for a full scrape. |
| `stats` | Tracker-wide totals instead — see [below](#get-scrapestats). |

**Repeat `info_hash` to scrape several torrents in one request.** Every hash
given is answered, so a client tracking twenty torrents makes one request rather
than twenty. This is the opposite of `announce`, where repeating `info_hash` is
meaningless and only the first valid one is used — an announce registers one
peer in one swarm.

```console
$ curl "https://tracker.example.com/scrape?json\
&info_hash=03148319face5909b193f1f980d0bcf9139a09ec\
&info_hash=90b3382caff769f4c7779ef90a5ab30eedda73d4"
```

The response is keyed by info_hash, one entry per torrent, in the order given:

```json
{
  "03148319face5909b193f1f980d0bcf9139a09ec": {
    "info_hash": "03148319face5909b193f1f980d0bcf9139a09ec",
    "seeders": 113,
    "leechers": 1,
    "peers": 114,
    "size": 3335405568,
    "downloads": 28693,
    "traffic": 95702791962624
  },
  "90b3382caff769f4c7779ef90a5ab30eedda73d4": {
    "info_hash": "90b3382caff769f4c7779ef90a5ab30eedda73d4",
    "seeders": 33,
    "leechers": 0,
    "peers": 33,
    "size": 3191691264,
    "downloads": 5403,
    "traffic": 17244707899392
  },
  "min_request_interval": 900
}
```

The flag and the hashes may appear in any order — `?info_hash=…&json&info_hash=…`
parses exactly like `?json&info_hash=…&info_hash=…`. Repeating the _same_ hash
is harmless: the response is keyed by info_hash, so duplicates collapse to one
entry.

There is no cap on how many hashes one request may carry, so a client with a
large library should still split very long requests: the limit you will hit is
your web server's maximum request line (commonly 8 KB, about 100 hashes),
not Phoenix.

**A hash the tracker does not know** behaves differently by tracker type:

- **Open tracker** — answered with every count at zero, which is what BEP 48
  asks for.
- **Closed tracker** — dropped from the response entirely, because it is not on
  the allowed list. Ask for two hashes and you get back only the allowed one.
  If _none_ of the requested hashes are allowed, the request errors with
  `Torrent is not allowed.` rather than falling through to a full scrape, so a
  caller cannot obtain the whole list by asking for a hash it may not see.

With no `info_hash` at all, a **full scrape** returns every torrent on the
tracker — which is what `full_scrape` controls. It ignores the allowed-torrents
filter, so turn it off when the list should be private.

`min_request_interval` (BEP 48) appears alongside the torrents when
`scrape_min_interval` is non-zero. A 40-hex info_hash can never collide with
that key. `traffic` is `size × downloads` — an estimate that counts no partial
or repeat downloads.

The bencode form uses BEP 48's standard `files` dict with `complete`,
`downloaded` and `incomplete`; the JSON and XML forms use the fuller shape
above.

## Public read endpoints

### GET / (torrent index)

The public torrent list, when `public_index` is on. HTML for browsers, with
`?json` and `?xml` for machines.

```json
[
  {
    "info_hash": "d83cd89ea7b75f433d5be5e222cb4eb8cf3a619a",
    "name": "elementary OS 6.0 Beta 1",
    "size": 2547646464,
    "downloads": 5846,
    "seeders": 0,
    "leechers": 0,
    "peers": 0,
    "traffic": 14893541228544,
    "filename": "elementaryos-6.0-daily.20210430.iso",
    "files": null,
    "trackers": ["https://tracker.ashrise.com/announce"],
    "webseeds": null,
    "magnet": "magnet:?xt=urn:btih:d83cd89ea7b75f433d5be5e222cb4eb8cf3a619a&dn=…"
  }
]
```

Only torrents with `listed = 1` appear. The meta fields (`filename`, `files`,
`trackers`, `webseeds`) are included only when `index_show_meta` is on; the
`magnet` link is built from whatever meta is present.

This endpoint is **unpaginated** — it renders every listed torrent.

### GET /scrape?stats

Tracker-wide totals. HTML by default, `?json` or `?xml` for machines.
Unauthenticated.

```json
{
  "tracker": {
    "version": "v4.3beta10",
    "peers": 346,
    "seeders": 343,
    "leechers": 3,
    "torrents": 26,
    "downloads": 1288564,
    "traffic": 2396397965244416
  }
}
```

The XML form puts the version on the root element:
`<tracker version="v4.3beta10">`.

**Changed in v4.3.** `version` was previously wrapped as `$Id: v4.3beta10 $,`
— a Subversion keyword inherited from PeerTracker, which git never expanded,
plus a trailing comma left behind when the response stopped being assembled by
hand. It is the bare version now, matching [`/api`](#get-api). A client that was
stripping the wrapper should stop.

## Management API

Everything under `/api/`. JSON by default, XML with `?xml`. There is no
bencode form and no central router — each endpoint is its own entry point.

Requests that fail authentication, validation or authorization return an error
body with HTTP 200, in the shape described in [Errors](#errors).

### Authentication

Two credential types, either of which resolves to the **user you act as**:

**1. A bearer key** — `Authorization: Bearer <key>`, checked against
`api_keys`. Create keys on the admin **API Keys** page; each is shown once and
only its SHA-256 hash is stored. No CSRF token is needed: a bearer key is not
an ambient browser credential, so it cannot be forged cross-site.

**2. A logged-in `admin.php` session**, which resolves to the `*` admin. Read
requests need no CSRF token (the response cannot be read cross-origin);
**write requests require a valid CSRF token**, because the session cookie is
sent automatically and a state change must prove intent.

Two kinds of user:

- **A named user** (`alice`) owns the torrents it adds and may only update,
  list, delist or delete those.
- **`*`** is the admin: it may act on any torrent, including announce-created
  rows that have no owner.

An empty `api_keys` disables key authentication entirely.

| Error | Cause |
| --- | --- |
| `Authorization required.` | No credential presented. |
| `API key is invalid.` | Key not in `api_keys`. |
| `API is not enabled.` | `api_keys` is empty. |
| `Method not allowed.` | GET on a POST endpoint, or the reverse. |
| `Torrent not found.` | No such torrent — **or** it exists and you do not own it. |

That last row is deliberate: a missing torrent and a torrent you cannot touch
are indistinguishable, so ownership never discloses existence.

### GET /api

Version probe. **Unauthenticated** — it exposes no torrent data, so clients can
check the API is present without a key.

```console
$ curl https://tracker.example.com/api/
{"phoenix":{"version":"v4.3beta10"}}
```

XML: `<phoenix><version>v4.3beta10</version></phoenix>`.

### GET /api/torrents

Every torrent the caller may see, with swarm stats. A named key sees its own
torrents; `*` sees all.

```console
curl -H "Authorization: Bearer $KEY" https://tracker.example.com/api/torrents
```

```json
{
  "torrents": [
    {
      "info_hash": "03148319face5909b193f1f980d0bcf9139a09ec",
      "user": "alice",
      "name": "elementary OS Circe",
      "size": 3335405568,
      "listed": 1,
      "downloads": 28693,
      "seeders": 112,
      "leechers": 1,
      "peers": 113,
      "traffic": 95702791962624,
      "filename": "elementaryos-0.3.2-stable-amd64.20151209.iso",
      "files": null,
      "trackers": ["https://tracker.example.com/announce"],
      "webseeds": null
    }
  ]
}
```

Unlisted torrents are included — API keys are operator-issued, so any valid key
is trusted with them. This endpoint is **unpaginated**.

### POST /api/torrent/add

Add a torrent, owned by the calling key's user. Add-only: an info_hash that is
already tracked is an error, so this endpoint can never rewrite — or take over —
an existing torrent.

| Field | Required | Notes |
| --- | --- | --- |
| `info_hash` | yes¹ | 40 hex chars. |
| `name` | no | Truncated to 255 characters. |
| `size` | no | Bytes; negatives floor to `0`. |
| `listed` | no | `0` hides from the public index. **Defaults to `1`.** |
| `filename`, `files`, `trackers`, `webseeds` | no | See [meta fields](#torrent-meta-fields). |
| `torrent` | no | A `.torrent` file, `multipart/form-data`. |

¹ Not required when you upload a `torrent` file, which supplies it.

Parameters are read from the POST body, falling back to the query string.

**Uploading a `.torrent`** parses it server-side and uses it as the base for
every field — info_hash, name, size, filename, files, trackers, webseeds. Any
explicitly posted parameter then overrides its parsed counterpart, but **only
when non-empty**, so a form posting blank fields cannot clobber the upload.
Uploads are capped at `torrent_upload_max`.

```console
$ curl -X POST -H "Authorization: Bearer $KEY" \
    -F "torrent=@ubuntu.torrent" -F "listed=1" \
    https://tracker.example.com/api/torrent/add
```

Responds with the stored torrent:

```json
{
  "torrent": {
    "user": "alice",
    "info_hash": "03148319face5909b193f1f980d0bcf9139a09ec",
    "name": "elementary OS Circe",
    "size": 3335405568,
    "listed": 1,
    "filename": "elementaryos-0.3.2-stable-amd64.20151209.iso",
    "files": null,
    "trackers": ["https://tracker.example.com/announce"],
    "webseeds": null
  }
}
```

The meta keys are always present so consumers can rely on a stable shape;
absent meta is `null`.

Errors: `Torrent already exists.`, `Info Hash is invalid.`,
`Torrent file is invalid.`, `Torrent file is too large.`

### POST /api/torrent/update

Edit an existing torrent. Same fields as `add`, minus the upload.

**Partial update**: a field present in the request replaces the stored value —
**including when empty**, which is how you clear one. An absent field keeps its
current value. `info_hash` identifies the row and cannot be changed; neither can
the owner or the `downloads` counter.

```console
$ curl -X POST -H "Authorization: Bearer $KEY" \
    -d "info_hash=0314…09ec" -d "name=elementary OS Circe" \
    https://tracker.example.com/api/torrent/update
```

Responds with the updated torrent, same shape as `add`.

Note that `update` can rewrite `trackers` and `webseeds`, so a key can repoint
its own torrents elsewhere. Unlike `delete`, it has no opt-in gate.

### POST /api/torrent/list and /api/torrent/delist

Show or hide a torrent on the public index. Takes `info_hash` only. Idempotent —
re-setting the current value still succeeds. Responds with the torrent, same
shape as `add`.

### POST /api/torrent/delete

Delete a torrent and its peers. Takes `info_hash` only. Responds with the
torrent as it was removed.

**Gated**: off by default. A named key is refused with
`Torrent deletion is disabled.` until you set `api_allow_delete = true`. The `*`
admin is always exempt. The gate is checked **before** the torrent is looked up,
so a tracker with deletion disabled discloses nothing about which torrents
exist.

**On an open tracker, deletion is not permanent.** The next announce for that
info_hash recreates the torrent, because an open tracker registers whatever is
announced to it. Deletion is only decisive on a closed tracker, where the
torrent is also absent from the allowed list. Removing the peers just makes the
swarm vanish immediately instead of expiring.

### Torrent meta fields

Four optional fields describe what a torrent contains. Each accepts more than
one input form, and each is returned in a normalized form.

| Field | Accepts | Returned as |
| --- | --- | --- |
| `filename` | A string | `string` or `null` |
| `files` | A JSON array of `{"path": …, "length": …}` | A list of objects, or `null` |
| `trackers` | A JSON array, or newline-separated URLs | A list of strings, or `null` |
| `webseeds` | A JSON array, or newline-separated URLs | A list of strings, or `null` |

A field that is absent, empty, or fully invalid comes back as `null`. Invalid
entries within an otherwise valid list are dropped rather than failing the
request.
