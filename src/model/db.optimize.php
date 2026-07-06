<?php

declare(strict_types=1);

/**
 * @param PhoenixSettings $settings
 * @param string $source who triggered the run: 'admin' or 'cron'
 */
function db_optimize(mysqli $connection, array $settings, int $time, string|false $table = false, bool $and_default = true, string $source = 'admin'): bool
{
    require_once __DIR__.'/task.log.php';

    $tables = [];
    if ($table) {
        $tables[] = $table;
    }
    if ($and_default) {
        $tables[] = 'peers';
        $tables[] = 'tasks';
        $tables[] = 'task_runs';
        $tables[] = 'torrents';
    }

    if (empty($tables)) {
        return true;
    }

    $sql = '';
    foreach ($tables as $t) {
        $sql .= 'CHECK TABLE `'.$settings['db_prefix'].$t.'`;'.
            'ANALYZE TABLE `'.$settings['db_prefix'].$t.'`;'.
            'REPAIR TABLE `'.$settings['db_prefix'].$t.'`;'.
            'OPTIMIZE TABLE `'.$settings['db_prefix'].$t.'`;';
    }
    $result = mysqli_multi_query($connection, $sql);
    // mysqli_multi_query buffers every result set internally; each must be
    // consumed before the connection is usable again. The first result is
    // already pending after the call returns, so the natural shape is a
    // do-while: store-and-free the current result, advance, repeat.
    if ($result) {
        do {
            $res = mysqli_store_result($connection);
            if ($res) {
                mysqli_free_result($res);
            }
        } while (mysqli_next_result($connection));
    }

    if ($result) {
        task_log($connection, $settings, 'optimize', $time, $source);
    }

    return $result;

}
