<?php

declare(strict_types=1);

////	config_write
// Rewrite phoenix.custom.php, applying only the changed keys in $values while
// preserving everything else already there (api_keys, stats_*, hand-edited
// tunables). Generalises install_build_config(): same var_export()-per-key
// serialisation. Returns true on success, false if the file could not be
// written.
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

    return file_put_contents($config_path, $config) !== false;
}
