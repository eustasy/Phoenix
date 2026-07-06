# Apache Configuration

Example configuration for serving Phoenix on Apache. Requires `mod_rewrite` (standard in
most distributions) and Apache 2.4.13+ for `CGIPassAuth`.

```apache
# Redirect plain HTTP to HTTPS
<VirtualHost *:80>
    ServerName tracker.example.com
    Redirect permanent / https://tracker.example.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName tracker.example.com
    DocumentRoot /var/www/phoenix/public

    # ... TLS configuration ...

    <Directory /var/www/phoenix/public>
        AllowOverride None
        Require all granted

        # Strip .php: /announce → /announce.php, /api/torrent/add → /api/torrent/add.php
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^([^.]+)$ $1.php [L]

        # Pass Authorization: Bearer to PHP (required for the management API under mod_php).
        # See the "Management API" section below if your Apache is older than 2.4.13.
        CGIPassAuth On
    </Directory>

    # Deny access to dotfiles (.git, .htaccess, etc.)
    <DirectoryMatch "/\.">
        Require all denied
    </DirectoryMatch>
</VirtualHost>
```

`DocumentRoot` must point at `public/`. Everything else in the Phoenix install
(`src/`, `bin/`, `config/`, `tests/`) lives one level above the document root
and is not web-reachable.

See the [Rate limiting](#rate-limiting) section below to protect the `/admin` endpoint.

## Stripping `.php`

The `RewriteRule` in the primary example maps extension-free URLs to their `.php` files
(`/announce` → `announce.php`, `/scrape` → `scrape.php`, `/index` → `index.php`,
`/admin` → `admin.php`, `/magnet` → `magnet.php`). The pattern `[^.]+` allows slashes,
so the nested API paths also resolve (`/api/torrent/add` → `public/api/torrent/add.php`).
The `!-f` / `!-d` conditions prevent the rule from rewriting requests for real files or
directories.

**Shared hosting:** if you can't edit the vhost (cPanel and similar), put those three
`Rewrite*` lines in a `public/.htaccess` file instead — such hosts enable `.htaccess`
overrides for you. The primary example uses `AllowOverride None` only because its rules
live in the vhost; `.htaccess` is needed solely when you don't control that.

## Management API (`Authorization` header)

The management API (`/api/...`) authenticates with an `Authorization: Bearer <key>` header.
`CGIPassAuth On` (included in the primary example) makes Apache expose that header to PHP
under mod_php.

**Older Apache (< 2.4.13):** copy the header into the environment instead:

```apache
SetEnvIf Authorization "(.+)" HTTP_AUTHORIZATION=$1
```

PHP-FPM (via `mod_proxy_fcgi`) and the built-in dev server pass `Authorization` through
without any of this.

After initial setup, consider removing `public/admin.php` from the server entirely — see
the install guide in [README.md](./README.md).

## Running behind a proxy (`forwarded_headers` / `trusted_proxies`)

By default Phoenix uses the connection's source address (`REMOTE_ADDR`) as the peer IP.
Behind a reverse proxy or load balancer that address is the proxy, not the client — the
real client IP arrives in a forwarded header (`X-Forwarded-For`, `Forwarded`, `X-Real-IP`,
`CF-Connecting-IP`, …) instead.

**Preferred:** let Apache rewrite the source address from a trusted proxy using
[`mod_remoteip`](https://httpd.apache.org/docs/2.4/mod/mod_remoteip.html), and leave
`forwarded_headers = []` (empty, the default):

```apache
RemoteIPHeader X-Forwarded-For
RemoteIPTrustedProxy 10.0.0.0/8   # your proxy's address(es) — never 0.0.0.0/0
```

`REMOTE_ADDR` then holds the real client, so Phoenix needs no proxy settings at all. This
keeps all forwarded-header handling in one place (the web server) and is the most robust
option.

**Alternative:** have Phoenix read the header itself. List the header(s) your proxy sets in
`forwarded_headers` and pin `trusted_proxies` to the proxy's CIDR range(s):

```php
$settings['forwarded_headers'] = ['x-forwarded-for']; // or ['cf-connecting-ip'], etc.
$settings['trusted_proxies']   = ['10.0.0.0/8'];       // your proxy's address(es)
```

A forwarded header is honored only when the direct connection (`REMOTE_ADDR`) falls inside
`trusted_proxies`, so a client connecting straight to the origin cannot spoof. For the chain
headers (`X-Forwarded-For`, `Forwarded`) Phoenix walks the chain from the right, skipping
your `trusted_proxies`, to find the real client — so it is safe whether your proxy appends
or overwrites the header (the old `RequestHeader set X-Forwarded-For` trick is no longer
needed).

Recognised `forwarded_headers` values: `x-forwarded-for`, `forwarded` (RFC 7239),
`x-real-ip`, `cf-connecting-ip`, `true-client-ip`, and the legacy `client-ip`. List **only**
the header(s) your proxy actually sets — every extra header you trust is one a misconfigured
proxy might pass through from the client. `client-ip` in particular is rarely stripped by
proxies; avoid it unless you specifically need it.

If your proxy has no stable IP range to pin (`trusted_proxies = []`), forwarded headers are
**ignored** unless you also set `allow_any_proxy = true` — an explicit, deliberately
insecure opt-in that trusts any connecting peer's header. Only use it when you fully control
who can reach the tracker.

## Rate limiting

`admin.php` is the highest-risk endpoint — it accepts a password and can drop or recreate
tables. Rate-limit it to slow brute-force attempts.

With [`mod_evasive`](https://github.com/jzdziarski/mod_evasive) enabled, scope its DoS
counters to the admin endpoint:

```apache
<Location "/admin.php">
    DOSPageCount 5
    DOSPageInterval 60
    DOSBlockingPeriod 600
</Location>
```

Five requests in any 60-second window blocks the source IP for 10 minutes. If you have
stripped `.php` (above), add a matching block for `/admin` so the unrewritten URL is
covered too.

For more aggressive blocking, pair with [fail2ban](https://www.fail2ban.org/) watching
the access log for repeated `/admin.php` requests.

A safer alternative is to remove `public/admin.php` from the server entirely after initial
setup — see the install guide in [README.md](./README.md).
