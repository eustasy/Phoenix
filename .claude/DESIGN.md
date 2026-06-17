# Phoenix — HTML Redesign Brief

A brief for redesigning every HTML surface Phoenix renders. Pass this to a
design pass; each section describes the page, the actions a user can take on it,
and example data so layouts can be mocked against realistic content.

## How the HTML is produced (read first)

Phoenix is a procedural-PHP BitTorrent tracker with no front-end build step. All
HTML is assembled as strings inside PHP **view functions** under
`src/views/html.*.php` (plus the self-contained `public/magnet.php`). To restyle
a surface you edit that view's markup/classes and its inline `<style>` — there
is no bundler, framework, or template engine.

Current styling, for reference (a redesign may keep, consolidate, or replace any
of it):

* Each surface links `normalize.css` and the eustasy **colors.css** (flat-UI
  palette) from jsDelivr with SRI hashes, then an inline `<style>` block.
* There is **no shared stylesheet.** The admin panel shares one `<style>` (in
  the layout); the login, installer, and magnet pages each carry their own; and
  the **public index and public stats pages have essentially no styling at all**
  (a bare `<table>` / `<pre>`).
* Flat-UI utility classes currently in use: `background-belize-hole` +
  `color-clouds` (primary buttons, blue), `background-pomegranate` (danger /
  errors, red), `background-green-sea` (success, green), `background-wisteria`
  (info messages, purple), `background-clouds` (neutral panel/badge),
  `color-asbestos` / `color-9` (muted text). Layout helpers: `.box`, `.button`,
  `.data-table`, `.admin-nav`, `.text-left/right/center`, `.float-left/right`,
  `body.wide`.
* All admin/login/installer pages declare `charset=UTF-8`; torrent names and
  client labels can contain any Unicode.

Constraints worth respecting: keep it dependency-light (no SPA framework),
server-rendered, and accessible without JavaScript except where JS is the
feature (drag-and-drop upload, the magnet generator).

## Surfaces at a glance

| Surface | File | Audience | Styled today? |
| --- | --- | --- | --- |
| Admin chrome (layout) | `src/views/html.admin.layout.php` | Operator | Yes (shared) |
| Admin: Dashboard | `src/views/html.admin.php` | Operator | Yes |
| Admin: Torrents | `src/views/html.admin.torrents.php` | Operator | Yes |
| Admin: Peers (drill-down) | `src/views/html.admin.torrent.peers.php` | Operator | Yes |
| Admin: Add a Torrent | `src/views/html.admin.add.php` | Operator | Yes |
| Admin: Edit Torrent | `src/views/html.admin.edit.php` | Operator | Yes |
| Admin: Server Support | `src/views/html.admin.support.php` | Operator | Yes |
| Admin: Utilities | `src/views/html.admin.utilities.php` | Operator | Yes |
| Admin: Backups | `src/views/html.admin.backups.php` | Operator | Yes |
| Admin: Settings | `src/views/html.admin.settings.php` | Operator | Yes |
| Login | `src/views/html.login.php` | Operator | Yes (own) |
| Installer / Setup | `src/views/html.install.php` | Operator | Yes (own) |
| Public: Torrent Index | `src/views/html.index.php` | Public | **No** |
| Public: Stats | `src/views/html.stats.php` | Public | **No** |
| Public: Magnet Generator | `public/magnet.php` | Public | Yes (own) |

The announce/scrape endpoints emit bencode/JSON/XML, never HTML; error responses
(`tracker_error`) are plain text/JSON/XML, not a styled page — out of scope.

---

## Admin chrome — shared layout

**File:** `src/views/html.admin.layout.php` (`view_admin_layout_html`)

**Description.** Wraps every admin page. Provides the `<head>` (title
`Phoenix Admin: <Page>`, charset, a small inline double-submit guard script,
stylesheets, base styles), a centered version line, an optional logout form, the
navigation bar, then the page-specific body. Pages opt into a wider column
(`body.wide`, ~1100px) for tables; otherwise the column is ~600px and centered.

**Actions.**

* Top navigation links: **Dashboard, Torrents, Add Torrent, Server Support,
  Utilities, Backups, Settings** (the active page is marked `aria-current` +
  `.current`).
* **Log out** — a POST form (CSRF token), shown only when an admin password is
  set.

**Example data.**

* Version line: `v4.3beta6`
* Page title: `Phoenix Admin: Torrents`
* Nav is always the same seven items; sub-pages (Peers, Edit) keep **Torrents**
  marked current.

---

## Admin — Dashboard

**File:** `src/views/html.admin.php` (`view_admin_html`) · page `?page=dashboard`

**Description.** Read-only landing page: a tracker-statistics overview plus the
last-run time of each maintenance task, and (on first load after install) a
confirmation banner. When the database is not installed yet, or there are no
stats, it shows a notice instead of figures.

**Actions.** None (links to other pages live in the nav). Empty states link to
**Utilities** / **Server Support**.

**Example data.**

* Stats block — `Seeders 42 · Leechers 7 · Peers 49`; `Registered torrents 318 ·
  With active peers 12`; `Completed downloads 1,337 · Traffic 6,442,450,944 bytes`.
* Last-run lines — `Last cleaned: 2026-06-14 10:15`, `Last optimized:
  2026-06-14 04:00` (any of install/migrate/clean/optimize that have run).
* Banners — `Installation complete.` (green) after setup; `The database is not
  installed yet.` (red) or `No tracker statistics yet.` (neutral) as empty states.

**Shared data.** This stats block and the public Stats page render the same
`stats_merge()` aggregation — treat them as one data model shown twice; the
Dashboard just adds the registered-torrent total and the maintenance timestamps.

---

## Admin — Torrents

**File:** `src/views/html.admin.torrents.php` (`view_admin_torrents_html`) ·
page `?page=torrents` · wide layout

**Description.** The torrent management table — every torrent, listed or not, any
owner — with per-row actions. Below it, a second table lists "unregistered
swarms": info_hashes with active peers but no torrents row (e.g. announced to an
open tracker, never registered).

**Actions (per row).**

* **Edit** (link → Edit page), **Peers** (link → drill-down).
* **List / Unlist** — POST form toggling public-index visibility.
* **Delete** — POST form (JS `confirm()`), removes the torrent and its peers.
* An optional action-result message banner at the top.

**Example data (main table columns: Name, Owner, Info Hash, Size, Seeders,
Leechers, Downloads, Traffic, Listed, Actions).**

```text
Ubuntu 24.04.1 LTS (amd64)   —        e72d508410198 7d12ad9…   5,150,212,096   42   7   1,337   …   Listed
Debian 12.7 netinst          alice    90b3382caff769f4c779…    702,545,920     3    0   58      …   Unlisted
```

* Owner is an API-key user (`alice`) or a dash (`—`) for admin/announce-created
  rows. Info Hash is a 40-char hex string. Name may be empty, long, or Unicode.
* Unregistered-swarms table columns: Info Hash, Seeders, Leechers, Peers,
  Actions (just a **Peers** link). Empty state: `No torrents are registered.`

---

## Admin — Peers (drill-down)

**File:** `src/views/html.admin.torrent.peers.php`
(`view_admin_torrent_peers_html`) · page `?page=peers&info_hash=…` · wide layout

**Description.** Every peer in one torrent's swarm. Titled by the torrent's name
when known, otherwise the raw info_hash (an unregistered swarm). Works for any
hash. The client is detected from the peer_id for display only (never stored).

**Actions.** A "← Back to Torrents" link. No mutating actions.

**Example data (columns: Client, Address, State, Up, Down, Left, Last seen).**

```text
Transmission 4.1.1.0   81.78.207.83:51413        Seeding    1,442,936   190,840,832       0   2026-06-14 11:02
qBittorrent 4.6.2.0    [2001:db8::1]:6881        Leeching            0   245,192,704  2,885,468,160   2026-06-14 11:01
µTorrent               203.0.113.9:6881           Leeching     12,288    98,566,144   1,073,741,824   2026-06-14 10:58
```

* Client labels: `Transmission 4.1.1.0`, `qBittorrent 4.6.2.0`, `Deluge`,
  `µTorrent`, `libtorrent`, or `Unknown`. Address may be IPv4, bracketed IPv6,
  both (two lines), or a dash. Empty state: `No active peers.`

---

## Admin — Add a Torrent

**File:** `src/views/html.admin.add.php` (`view_admin_add_html`) ·
page `?page=add` · wide layout

**Description.** A form to register a torrent, either by typing the fields or by
uploading a `.torrent` (parsed server-side, with a drag-and-drop zone that feeds
a file input). When the database isn't installed, the form is replaced by a
pointer to Utilities.

**Actions.**

* Text/number fields: **Name, Info Hash, Size (bytes), Filename**; textareas:
  **Files (JSON), Trackers (one per line), Web Seeds (one per line)**; a
  **Listed on the public index** checkbox (checked by default).
* A drag-and-drop / file-picker zone for a `.torrent`.
* **Add Torrent** submit (POST, multipart, CSRF). Result message on return.

**Example data.**

* Info Hash: `e72d508410198 7d12ad9d33468f759439c3133b` (40 hex).
* Files JSON: `[{"path":"ubuntu-24.04.1.iso","length":5150212096}]`.
* Trackers: `https://tracker.example.com/announce.php` (one per line).
* Result banners: `Torrent added.`, `Torrent already exists.`,
  `Torrent file is invalid.`

---

## Admin — Edit Torrent

**File:** `src/views/html.admin.edit.php` (`view_admin_edit_html`) ·
page `?page=edit&info_hash=…` · wide layout

**Description.** The same field set as Add, pre-filled from an existing torrent
(meta rendered back into the form's request shape). The info_hash is shown
read-only. When the torrent is missing, a notice replaces the form.

**Actions.** **Save Changes** (POST, CSRF, full replace); a "← Back to Torrents"
link. Result message on return.

**Example data.**

* Read-only Info Hash header: `<code>e72d50841019…</code>`.
* Pre-filled values: Name `Ubuntu 24.04.1 LTS (amd64)`, Size `5150212096`,
  Listed checked, Files `[{"path":"ubuntu-24.04.1.iso","length":5150212096}]`,
  Trackers one per line.
* Banners: `Torrent updated.`, `Torrent not found.`

---

## Admin — Server Support

**File:** `src/views/html.admin.support.php` (`view_admin_support_html`) ·
page `?page=support`

**Description.** Read-only diagnostics: PHP version support, MySQL/ext-mysqli
availability and client version, whether all tables are installed, and the
database size. No forms.

**Example data.**

* `Your PHP version is supported.` (green) · `PHP Version: 8.4.7`
* `Your server supports MySQL.` (green) · `MySQL Version: 8.3.0`
* `All your tables are installed. Their current size is 4,718,592 bytes.`
* Failure variants (red): `Phoenix requires PHP >= 8.2.`, `Your server does not
  support MySQL.`, `Some or all of your tables are not installed.` (links to
  Utilities).

---

## Admin — Utilities

**File:** `src/views/html.admin.utilities.php` (`view_admin_utilities_html`) ·
page `?page=utilities`

**Description.** Database maintenance actions, each a one-line label with a
button. The setup/reset form also appears when tables are missing; clean /
optimize / migrate require installed tables.

**Actions (each a POST form, CSRF).**

* **Setup** — install / upgrade / reset the database (shows a red warning about
  setting `db_reset` to false afterward), or a `Disabled` badge when locked.
* **Clean** (out redundant peers), **Optimize** (check/analyze/repair/optimize),
  **Upgrade Schema** (idempotent migrations).

**Example data.** Result banners: `The peers list has been cleaned.`, `Your
MySQL Tracker Database has been optimized.`, `Your MySQL Tracker Database has
been setup.`, `Security check failed. Please reload the page and try again.`

---

## Admin — Backups

**File:** `src/views/html.admin.backups.php` (`view_admin_backups_html`) ·
page `?page=backups` · wide layout

**Description.** Run an on-demand database backup and list existing dumps with
download links. A note flags the `mysqldump` / writable-directory requirement.

**Actions.** **Run backup now** (POST, CSRF); per-row **Download** link
(`?page=backups&download=<file>`).

**Example data (columns: File, Size, Created, Actions).**

```text
phoenix.2026-06-14_03-00-00.sql   4,718,592 bytes   2026-06-14 03:00   Download
phoenix.2026-06-13_03-00-00.sql   4,702,208 bytes   2026-06-13 03:00   Download
```

* Empty state: `No backups yet.` Result banners: a success line or the dump
  engine's error string.

---

## Admin — Settings

**File:** `src/views/html.admin.settings.php` (`view_admin_settings_html`) ·
page `?page=settings` · wide layout

**Description.** A read-only table of the effective settings (secrets masked),
then — when `config/` is writable — a password-change form, a two-factor
enable/disable form (when the verification library is present), and the flag
toggles. When `config/` isn't writable, the forms are replaced by a note.

**Actions.**

* **Change Password** (POST, CSRF).
* **Enable 2FA** (shows a QR + secret, requires a confirming code) or **Disable
  2FA** (requires a current code).
* **Save Flags** — checkboxes for `open_tracker`, `public_index`, `full_scrape`
  (with a red warning note), `db_reset`.

**Example data.**

* Settings table rows (key / value): `db_host` = `localhost`, `db_pass` =
  `********`, `admin_password` = `********`, `admin_totp_secret` = `********` (or
  `(not set)`), `api_keys` = `2 keys configured (values hidden)`, `open_tracker`
  = `true`, `announce_interval` = `1800`, `trusted_proxies` = `(empty)`.
* 2FA enable: a QR `<img>` (data URI), secret `JBSWY3DPEHPK3PXP`, a 6-digit code
  field. Enabled state: `Two-factor authentication is enabled.` (green).
* Banners: `Admin password changed.`, `Settings saved.`, `Two-factor
  authentication enabled.` / `disabled.`

---

## Login

**File:** `src/views/html.login.php` (`view_login_html`) — standalone page
(not the admin layout)

**Description.** The admin login form. Narrow (~400px), centered. Shown whenever
an admin password is set and there's no valid session.

**Actions.** **Log In** (POST): a **Password** field, plus a 6-digit
**Authentication Code** field when 2FA is enabled.

**Example data.** Heading `Phoenix`. Error banner (red): `Incorrect password.`

---

## Installer / Setup

**File:** `src/views/html.install.php` (`view_install_html`) — standalone page

**Description.** First-run setup, shown when no `config/phoenix.custom.php`
exists. Collects database credentials, tracker options, an admin password, and
optional 2FA enrolment. When `config/` isn't writable, the form is replaced by a
warning.

**Actions.** **Install** (POST). Fields grouped under **Database** (Host,
Username, Password, Database Name, Table Prefix, Persistent Connections
checkbox), **Tracker** (Open Tracker, Public Index checkboxes), **Admin** (Admin
Password, required), and **(Optional) Two-Factor Authentication** (QR image +
6-digit code; or a manual secret + `otpauth://` link when GD is unavailable).

**Example data.** Heading `Phoenix Setup`. Defaults: Host `localhost`, Table
Prefix `phoenix_`. 2FA secret `JBSWY3DPEHPK3PXP`, otpauth URL
`otpauth://totp/phoenix:admin?secret=…&issuer=Phoenix`. Errors (red): `Set an
admin password to protect the control panel.`, `The two-factor code was
incorrect.`, `config/ is not writable.`

---

## Public — Torrent Index

**File:** `src/views/html.index.php` (`view_index_html`) — **currently
unstyled** (a bare `<table>`, no CSS)

**Description.** The optional public listing of explicitly-listed torrents (gated
by the `public_index` setting). A high-value redesign target: it's the only
public-facing browse page and today has no styling at all. Columns expand when
the `index_show_meta` setting is on.

**Actions.** Per-row **magnet** link. No forms. (Could gain search/sort/paging in
a redesign — none exist today.)

**Example data.**

* Base columns: Title, Hash, Seeders, Leechers, Tracked Downloads, Health,
  Magnet. With meta on, add **File, Trackers, Webseeds** columns.
* Health is the seeder share of the swarm as a percentage (e.g. `86%`), or a
  dash for an empty swarm.

```text
Ubuntu 24.04.1 LTS (amd64)   e72d50841019…   42   7   1,337   86%   magnet
Debian 12.7 netinst          90b3382caff7…    3   0      58   100%  magnet
```

* Magnet href: `magnet:?xt=urn:btih:e72d…&dn=Ubuntu…&tr=https://…/announce.php`.

**Design direction.**

* This is the only public *browse* page — make it clean, scannable, and
  mobile-friendly, in Phoenix's flat-UI look. Reuse the same stylesheets the
  other pages link (`normalize.css` + eustasy `colors.css`), or a small
  self-contained sheet; either inline a `<style>` in `view_index_html` or link a
  static sheet from `public/`.
* Give it a real `<head>`: the page currently emits no charset/viewport/styles.
  Add `<meta charset="UTF-8">`, **`<meta name="viewport" content="width=device-width, initial-scale=1">`**
  (there is none today, so it's unusable on phones), and a meaningful `<title>`.
* Add a page header — a heading (tracker name / "Torrent Index") and ideally a
  torrent count — above the table.
* Table: zebra striping, a clear (optionally sticky) header row, comfortable
  padding, numeric columns (Seeders/Leechers/Tracked Downloads) right-aligned,
  Title left-aligned. The 40-char Info Hash should be monospaced and
  truncated/wrapped (an optional click-to-copy is a nice touch; keep it
  JS-optional).
* **Health** should read at a glance — render the percentage as a small colored
  pill or mini-bar that trends green (high seeder share) → amber → red (low),
  with the `%` as its label; keep the em-dash for an empty swarm. The flat-UI
  classes (`background-green-sea`, `background-pomegranate`, …) suit this.
* **Magnet** should be an obvious button/link (the `href` is already built);
  keep it keyboard-focusable.
* Meta columns (only when `index_show_meta` is on): File is a filename; Trackers
  and Webseeds are newline-joined URL lists — render them compactly (small /
  monospace, wrap or scroll) so they don't blow out row height.
* Responsive: on narrow screens, allow the table to scroll horizontally or
  collapse to stacked cards. Style by column meaning, not fixed position — the
  column set changes with `index_show_meta`.
* Replace the empty `<tbody>` with a friendly empty state ("No torrents are
  listed."). Use `<th scope="col">` and keep contrast/focus styles accessible.

**Keep in sync (tests).** `tests/phoenix/ViewIndexHtmlTest.php` asserts on exact
markup: the output starts with `` `<!DocType html>` `` (note the casing); the
headers `` `<th>Title</th>` ``, `Hash`, `Seeders`, `Leechers`,
`Tracked Downloads`, `Health`, `Magnet` (plus `File`/`Trackers`/`Webseeds` when
meta is on); exact cells such as `` `<td>Test Torrent</td>` ``,
`` `<td>67%</td>` ``, `` `<td>0%</td>` ``, `` `<td>&mdash;</td>` ``; an empty
`` `<tbody></tbody>` ``; and HTML-escaping (`` `&lt;script&gt;` ``). Wrapping a
cell's value (e.g. health in `<span class="pill">67%</span>`), renaming a
header, or changing the doctype string will fail those assertions — update that
test file alongside the redesign so CI (PHPUnit) stays green.

---

## Public — Stats

**File:** `src/views/html.stats.php` (`view_stats_html`) — **currently unstyled**
(a single `<pre>` line)

**Description.** A one-line tracker summary, served by `scrape.php?stats` in HTML
mode. Today it's a single `<pre>` sentence with no styling.

**Shared data.** Same `stats_merge()` figures as the admin Dashboard's "Tracker
Stats" block (minus the registered total and maintenance timestamps) — one data
model, two views.

**Actions.** None (read-only).

**Example data.**

```text
49 peers (42 seeders + 7 leechers) in 12 torrents and 1,337 downloads completed, 6,442,450,944 bytes served.
```

**Design direction.**

* Served by `scrape.php?stats` — a small public status page. Replace the single
  `<pre>` sentence with a styled summary, but keep it lightweight (no heavy
  assets); reuse the same stylesheets as the other pages or a tiny inline sheet.
* Give it a proper `<head>` (charset, **viewport**, the existing `<title>`) and a
  centered container.
* Present the figures as a small set of **stat cards** (or a definition list) in
  a responsive grid: a prominent **Peers** figure with a **Seeders + Leechers**
  breakdown, then **Torrents**, **Completed downloads**, and **Traffic**. Big
  numbers, muted labels.
* Traffic is a raw byte count and can be huge — consider showing a human-readable
  form (e.g. `6.0 GB`) *alongside* the exact bytes rather than replacing it.
* Keep contrast/focus accessible; no interactions needed (read-only).

**Keep in sync (tests).** `tests/phoenix/ViewStatsHtmlTest.php` asserts the
output contains `` `<!DocType html>` ``, the exact
`` `<title>Phoenix: $Id: 1.0.0 $</title>` ``, and the **contiguous** strings
`15 peers`, `10 seeders`, `5 leechers`, `3 torrents`, `100 downloads`, and
`5,000,000 bytes` (with large-number variants). Splitting a number from its
label across elements (e.g. `<span>15</span> peers`) breaks the `15 peers`
assertion, and changing the `<title>` breaks that one — so keep each
"number + label" as one text run (and the title) or update that test file with
the redesign.

---

## Public — Magnet Generator

**File:** `public/magnet.php` — self-contained page (its own HTML, CSS, and
client-side JavaScript; does **not** bootstrap the tracker)

**Description.** A client-side tool: drop a `.torrent` (or browse), and the page
parses it in the browser (bencode + SHA-1), then lets you edit the magnet
parameters and copy the resulting link. Narrow (~640px). The tracker's own
announce URL is pre-filled as the first tracker. Already the most polished page
(styled drop zone, live preview).

**Actions.**

* Drop zone / file picker for a `.torrent`.
* Editable fields: **xt** (Info Hash, read-only), **dn** (Display Name), **xl**
  (Size), **tr** (Trackers), **ws** (Web Seeds), **xs** (Exact Source), **as**
  (Acceptable Source), **kt** (Keyword Topic).
* A read-only **Magnet Link** output and a **Copy** button.

**Example data.**

* xt `e72d508410198 7d12ad9d33468f759439c3133b`, dn `Ubuntu 24.04.1 LTS (amd64)`,
  xl `5150212096`, tr `https://tracker.example.com/announce.php`.
* Output: `magnet:?xt=urn:btih:e72d…&dn=Ubuntu+24.04.1…&xl=5150212096&tr=https%3A%2F%2F…`.
* Error banner (red): `Please drop a .torrent file.`,
  `Torrent file is not a bencode dict.`

---

## Suggested priorities

1. **Public Torrent Index** and **Public Stats** — unstyled and public-facing;
   the biggest visual wins.
2. **Admin chrome + the data tables** (Torrents, Peers, Backups, Settings) — one
   shared design system carries every admin page.
3. **Login / Installer** — first impressions; small, self-contained pages.
4. **Magnet Generator** — already polished; align it with the new system.
