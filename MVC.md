# Phoenix 4 MVC Rewrite

## General
- Stay procedural and keep one function per file.
- Update tests with changes.

## Views
```
src/
  views/
    bencode.scrape.php      # Handles bencoded scrape responses
    bencode.announce.php    # Handles bencoded announce responses
    html.index.php          # Renders the public torrent index in HTML
    html.stats.php          # Renders tracker stats in HTML
```

- XML and JSON outputs remain as simple built-in functions, not full views, since they’re just array-to-format conversions.
- All bencode protocol responses are handled by dedicated view files, with normalization as needed per endpoint.
- HTML views are separated for index and stats, making them easy to maintain and extend.
