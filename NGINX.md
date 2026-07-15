# Nginx Configuration

Example configuration for serving Phoenix on Nginx. Adjust paths and the PHP-FPM socket
to match your environment.

```nginx
# Redirect plain HTTP to HTTPS
server {
    listen 80;
    server_name tracker.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name tracker.example.com;

    root /var/www/phoenix/public;
    index index.php;

    # ... TLS configuration ...

    # Deny dotfiles (.git, .htaccess, etc.)
    location ~ /\. {
        deny all;
    }

    # Strip .php: /announce → /announce.php, /api/torrent/add → /api/torrent/add.php
    location / {
        try_files $uri $uri/ $uri.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php-fpm.sock;
        fastcgi_index index.php;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
    }
}
```

`root` must point at `public/`. Everything else in the Phoenix install
(`src/`, `bin/`, `config/`, `tests/`) lives one level above the document root
and is not web-reachable.

Nginx with PHP-FPM passes the `Authorization: Bearer` header through to PHP automatically;
no extra configuration is needed for the management API.

See the [Rate limiting](#rate-limiting) section below to protect the `/admin` endpoint.

## Stripping `.php`

The `try_files $uri $uri/ $uri.php$is_args$args;` directive in the primary example maps
extension-free URLs to their `.php` files via an internal rewrite: `/announce` →
`announce.php`, `/scrape` → `scrape.php`, `/index` → `index.php`, `/admin` → `admin.php`,
`/magnet` → `magnet.php`. `try_files` already resolves subdirectories, so nested API paths
also resolve (`/api/torrent/add` → `public/api/torrent/add.php`) without any extra rules.

## Running behind a proxy (`forwarded_headers` / `trusted_proxies`)

By default Phoenix uses the connection's source address (`REMOTE_ADDR`) as the peer IP.
Behind a reverse proxy or load balancer that address is the proxy, not the client — the
real client IP arrives in a forwarded header (`X-Forwarded-For`, `Forwarded`, `X-Real-IP`,
`CF-Connecting-IP`, …) instead.

**Preferred:** let Nginx rewrite the source address from a trusted proxy using the
[realip module](https://nginx.org/en/docs/http/ngx_http_realip_module.html), and leave
`forwarded_headers = []` (empty, the default):

```nginx
set_real_ip_from 10.0.0.0/8;   # your proxy's address(es) — never 0.0.0.0/0
real_ip_header   X-Forwarded-For;
real_ip_recursive on;
```

`$remote_addr` (and therefore `REMOTE_ADDR`) then holds the real client, so Phoenix needs
no proxy settings at all. This is the most robust option.

**Alternative:** have Phoenix read the header itself. List the header(s) your proxy sets in
`forwarded_headers` and pin `trusted_proxies` to the proxy's CIDR range(s):

```php
$settings['forwarded_headers'] = ['x-forwarded-for']; // or ['cf-connecting-ip'], etc.
$settings['trusted_proxies']   = ['10.0.0.0/8'];       // your proxy's address(es)
```

with the usual upstream header:

```nginx
proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
```

A forwarded header is honored only when the direct connection (`REMOTE_ADDR`) falls inside
`trusted_proxies`, so a client connecting straight to the origin cannot spoof. For the chain
headers (`X-Forwarded-For`, `Forwarded`) Phoenix walks the chain from the right, skipping
your `trusted_proxies`, to find the real client — so the common appending form above
(`$proxy_add_x_forwarded_for`) is now safe: a value a client pre-injects sits to the left of
the real address and is never used.

Recognised `forwarded_headers` values: `x-forwarded-for`, `forwarded` (RFC 7239),
`x-real-ip`, `cf-connecting-ip`, `true-client-ip`, and the legacy `client-ip`. List **only**
the header(s) your proxy actually sets. `client-ip` in particular is rarely stripped by
proxies; avoid it unless you specifically need it.

If your proxy has no stable IP range to pin (`trusted_proxies = []`), forwarded headers are
**ignored** unless you also set `trust_any_forwarded = true` — an explicit, deliberately
insecure opt-in that trusts any connecting peer's header. Only use it when you fully control
who can reach the tracker.

## Rate limiting

`admin.php` is the highest-risk endpoint — it accepts a password and can drop or recreate
tables. Rate-limit it to slow brute-force attempts.

Declare a zone once in the `http` block of `nginx.conf`:

```nginx
http {
    limit_req_zone $binary_remote_addr zone=phoenix_admin:10m rate=10r/m;
}
```

Then apply it inside the `server` block, before the generic `\.php$` location so the
exact match wins:

```nginx
location = /admin.php {
    limit_req zone=phoenix_admin burst=5 nodelay;

    fastcgi_pass unix:/run/php/php-fpm.sock;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

`try_files` performs an internal rewrite to `/admin.php`, so requests to `/admin` (with
`.php` stripped) match this block too.

A safer alternative is to remove `public/admin.php` from the server entirely after initial
setup — see the install guide in [README.md](./README.md).
