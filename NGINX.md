# Nginx Configuration

Example configuration for serving Phoenix on Nginx. Adjust paths and the PHP-FPM socket to match your environment.

```nginx
server {
    listen 443 ssl http2;
    server_name tracker.example.com;

    root /var/www/phoenix/public;
    index index.php;

    # ... TLS configuration ...

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

`root` must point at `public/`. Everything else in the Phoenix install (`src/`, `bin/`, `config/`, `tests/`) lives one level above the document root and is not web-reachable.

## Stripping `.php`

The `try_files $uri $uri/ $uri.php$is_args$args;` directive in the snippet above maps `/announce` to `/announce.php` via an internal rewrite, so clients can announce to `https://tracker.example.com/announce` without the `.php` suffix. The same applies to `/scrape`, `/index`, and `/admin`.

## Running behind a proxy (`X-Forwarded-For` / `honor_xff`)

By default Phoenix uses the connection's source address (`REMOTE_ADDR`) as the peer IP. Behind a reverse proxy or load balancer that address is the proxy, not the client — the real client IP arrives in the `X-Forwarded-For` header instead.

**Preferred:** let Nginx rewrite the source address from a trusted proxy using the [realip module](https://nginx.org/en/docs/http/ngx_http_realip_module.html), and leave `honor_xff = false`:

```nginx
set_real_ip_from 10.0.0.0/8;   # your proxy's address(es) — never 0.0.0.0/0
real_ip_header   X-Forwarded-For;
real_ip_recursive on;
```

`$remote_addr` (and therefore `REMOTE_ADDR`) then holds the real client, so Phoenix needs no special setting.

**Alternative:** set `honor_xff = true` in `config/phoenix.custom.php`, which trusts the leftmost `X-Forwarded-For` entry. Only do this if your proxy **overwrites** the header so a client cannot supply it:

```nginx
# UNSAFE with honor_xff: appends, so a client-sent value survives as the leftmost entry.
proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;

# Safe: overwrite with the connecting address.
proxy_set_header X-Forwarded-For $remote_addr;
```

If Phoenix trusts an unsanitized header, any client can spoof its IP — poisoning swarms with arbitrary peer addresses (reflective DDoS) and evading the per-IP rate limiter. To restrict trust, set `trusted_proxies` in `config/phoenix.custom.php` to your proxy's CIDR range(s); `X-Forwarded-For` is then honored only from connections within those ranges (leave empty to honor it from any peer). If this server is reachable directly (not only through the proxy), set `trusted_proxies` or keep `honor_xff = false`.

## Rate limiting

`admin.php` is the highest-risk endpoint — it accepts a password and can drop or recreate tables. Rate-limit it to slow brute-force attempts.

Declare a zone once in the `http` block of `nginx.conf`:

```nginx
http {
    limit_req_zone $binary_remote_addr zone=phoenix_admin:10m rate=10r/m;
}
```

Then apply it inside the `server` block, before the generic `\.php$` location so the exact match wins:

```nginx
location = /admin.php {
    limit_req zone=phoenix_admin burst=5 nodelay;

    fastcgi_pass unix:/run/php/php-fpm.sock;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

`try_files` performs an internal rewrite to `/admin.php`, so requests to `/admin` (with `.php` stripped) match this block too.

A safer alternative is to remove `public/admin.php` from the server entirely after initial setup — see the install guide in [README.md](./README.md).
