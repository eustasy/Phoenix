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
RewriteRule ^([^/.]+)$ $1.php [L]
```

The same rule covers `/scrape`, `/index`, and `/admin`.

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
