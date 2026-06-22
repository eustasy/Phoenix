# Views & output

`src/views/` is the presentation layer. Views receive **normalized data arrays**
— never raw DB results or `$_GET`/`$_POST`. One function per file (see
[conventions.md](conventions.md)).

## Output families

- **`bencode.*.php`** — the BitTorrent protocol responses (announce, scrape,
  error). The canonical output of announce/scrape.
- **`json.*.php`** / **`xml.*.php`** — debug/alternate serializations of the same
  data, plus the REST API's JSON/XML responses.
- **`html.*.php`** — human-facing pages (admin panel, login, installer, public
  index, public stats). The admin pages share `html.admin.layout.php`; login,
  installer, and magnet carry their own styles. There is no shared stylesheet
  and no build step — markup and inline `<style>` live in the view function.
  Full surface-by-surface brief in [DESIGN.md](../DESIGN.md).

## Content negotiation

Controllers pick the serialization from `$_GET` flags and set the matching
`Content-Type`:

- announce/scrape: bencode by default (`text/plain; charset=ISO-8859-1`),
  `?json` → JSON, `?xml` → XML.
- API entry points pre-set `$_GET['json']` before bootstrap, so the default is
  JSON and `?xml` switches to XML — and even a bootstrap-time `tracker_error()`
  serialises correctly instead of emitting bencode.

`tracker_error()` reads the same `$_GET` flags to choose its own serialization
(plain text / JSON / XML — never a styled HTML page).

## The bencode emitter contract

**Bencode is never hand-assembled in a view.** The `bencode.*.php` views build a
plain PHP structure (ints, byte strings, lists, dicts) and hand it to
`bencode_encode()` (`src/functions/bencode.encode.php`) — the single emitter. It
owns:

- Length prefixes and balanced container tokens.
- **Dict key ordering** — keys are sorted into raw byte order
  (`ksort(SORT_STRING)`) per BEP 3, so callers never pre-sort.
- Container typing: an empty PHP array encodes as an empty **list** (`le`). To
  force a **dict** (`de`), cast to `(object)` — as `view_scrape_bencode` does for
  the binary-keyed, possibly-empty files dict.

`bencode.decode.*.php` is the matching decoder (used by `torrent_parse()` and
covered by round-trip tests).

## Cast numbers before encoding

mysqli returns numeric columns as strings, which bencode/JSON would emit as
strings rather than integers. Cast to `int` in the model or before encoding. See
[database.md](database.md).
