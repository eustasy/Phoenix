<?php

declare(strict_types=1);

////	config_write
// Rewrite phoenix.custom.php, applying only the changed keys in $values while
// preserving everything else already there (api_keys, stats_*, hand-edited
// tunables). Generalises install_build_config(): same var_export()-per-key
// serialisation. Returns true on success, false if the file could not be
// written. The write is published atomically (sibling temp file + rename) so a
// concurrent settings_load() never observes a half-written config.
//
// The existing custom file is re-read in an isolated scope (it only assigns
// $settings['...'], so a fresh local $settings captures exactly its keys),
// then $values is merged over it — callers pass just the keys they change.
// The 'p:' persistent-connection prefix db_connect() prepends to db_host in
// memory is stripped before writing (documented gotcha, also handled in
// bin/backup-database.php).

/** @param array<string, mixed> $values */
function config_write(string $config_path, array $values): bool
{
    $current = (static function (string $path): array {
        $settings = [];
        if (is_readable($path)) {
            include $path;
        }

        return $settings;
    })($config_path);

    $merged = array_merge($current, $values);

    if (
        isset($merged['db_host'])
        && is_string($merged['db_host'])
        && strncmp($merged['db_host'], 'p:', 2) === 0
    ) {
        $merged['db_host'] = substr($merged['db_host'], 2);
    }

    $config = '<?php'.PHP_EOL.PHP_EOL;
    foreach ($merged as $key => $value) {
        $config .= '$settings['.var_export((string) $key, true).'] = '.var_export($value, true).';'.PHP_EOL;
    }

    // Publish atomically. settings_load() include()s this file on EVERY request
    // (announce included), so a torn in-place write would let a concurrent read
    // see a half-written file and fatal the whole tracker until it is fixed by
    // hand. Instead write the new source to a sibling temp file — under an
    // exclusive lock for the duration of the write — then rename() it over the
    // target: rename() within one directory is atomic on POSIX, so a reader
    // always sees either the whole old file or the whole new one. The temp lives
    // in the same directory so the rename never crosses filesystems. Existing
    // permissions are preserved (a brand-new file defaults to owner-only,
    // matching the sensitive credentials the file holds).
    // Bail up front when the target directory isn't writable, rather than let
    // tempnam() silently fall back to the system temp dir — which would put the
    // temp on a different filesystem, fail the same-directory rename below, and
    // log a notice. Mirrors the writability the admin callers already check.
    $dir = dirname($config_path);
    if (! is_writable($dir)) {
        return false;
    }

    $tmp = tempnam($dir, '.phxcfg_');
    if ($tmp === false) {
        return false;
    }

    if (file_put_contents($tmp, $config, LOCK_EX) === false) {
        @unlink($tmp);

        return false;
    }

    $perms = @fileperms($config_path);
    @chmod($tmp, $perms !== false ? ($perms & 0o777) : 0o600);

    if (! @rename($tmp, $config_path)) {
        @unlink($tmp);

        return false;
    }

    return true;
}
