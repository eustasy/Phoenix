# Apache Configuration

Example configuration for serving Phoenix on Apache.

```apache
<VirtualHost *:443>
    ServerName tracker.example.com
    DocumentRoot /var/www/phoenix/public

    <Directory /var/www/phoenix/public>
        AllowOverride All
        Require all granted
    </Directory>

    # ... TLS configuration ...
</VirtualHost>
```

`DocumentRoot` must point at `public/`. Everything else in the Phoenix install (`src/`, `bin/`, `config/`, `tests/`) lives one level above the document root and is not web-reachable.

## Stripping `.php`

Place the following in `public/.htaccess` (or in the `<Directory>` block above) to map `/announce` to `/announce.php`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^([^.]+)$ $1.php [L]
```

The same rule covers `/scrape`, `/index`, and `/admin`. The pattern intentionally
allows slashes (`[^.]+`, not `[^/.]+`) so the nested API paths
(`/api/torrent/add` → `public/api/torrent/add.php`) resolve too; the `!-f` / `!-d`
conditions keep it from clobbering real files or directories.

## Running behind a proxy (`X-Forwarded-For` / `honor_xff`)

By default Phoenix uses the connection's source address (`REMOTE_ADDR`) as the peer IP. Behind a reverse proxy or load balancer that address is the proxy, not the client — the real client IP arrives in the `X-Forwarded-For` header instead.

**Preferred:** let Apache rewrite the source address from a trusted proxy using [`mod_remoteip`](https://httpd.apache.org/docs/2.4/mod/mod_remoteip.html), and leave `honor_xff = false`:

```apache
RemoteIPHeader X-Forwarded-For
RemoteIPTrustedProxy 10.0.0.0/8   # your proxy's address(es) — never 0.0.0.0/0
```

`REMOTE_ADDR` then holds the real client, so Phoenix needs no special setting.

**Alternative:** set `honor_xff = true` in `config/phoenix.custom.php`, which trusts the leftmost `X-Forwarded-For` entry. Only do this if your proxy **overwrites** the header so a client cannot supply it:

```apache
RequestHeader set X-Forwarded-For "%{REMOTE_ADDR}s"
```

If Phoenix trusts an unsanitized header, any client can spoof its IP — poisoning swarms with arbitrary peer addresses (reflective DDoS) and evading the per-IP rate limiter. To restrict trust, set `trusted_proxies` in `config/phoenix.custom.php` to your proxy's CIDR range(s); `X-Forwarded-For` is then honored only from connections within those ranges (leave empty to honor it from any peer). If this server is reachable directly (not only through the proxy), set `trusted_proxies` or keep `honor_xff = false`.

## Rate limiting

`admin.php` is the highest-risk endpoint — it accepts a password and can drop or recreate tables. Rate-limit it to slow brute-force attempts.

With [`mod_evasive`](https://github.com/jzdziarski/mod_evasive) enabled, scope its DoS counters to the admin endpoint:

```apache
<Location "/admin.php">
    DOSPageCount 5
    DOSPageInterval 60
    DOSBlockingPeriod 600
</Location>
```

Five requests in any 60-second window blocks the source IP for 10 minutes. If you have stripped `.php` (above), add a matching block for `/admin` so the unrewritten URL is covered too.

For more aggressive blocking, pair with [fail2ban](https://www.fail2ban.org/) watching the access log for repeated `/admin.php` requests.

A safer alternative is to remove `public/admin.php` from the server entirely after initial setup — see the install guide in [README.md](./README.md).
