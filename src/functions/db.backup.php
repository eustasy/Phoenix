<?php

declare(strict_types=1);

////	db_backup
// Dump the database into a dated directory — one schema file plus one data file
// per table — then rotate out directories older than backup_retention days. The
// engine for both bin/backup-database.php (cron) and the admin Backups page, so
// it returns a result array rather than echoing/exiting:
//   ['ok' => bool, 'file' => ?string (directory path on success), 'error' => ?string]
//
// Layout, with backup_compress on (the default) every file also gets .gz:
//   backups/<db_name>.<Ymd_His>/schema.sql     every table's CREATE, + routines/triggers
//   backups/<db_name>.<Ymd_His>/torrents.sql   data only
//   backups/<db_name>.<Ymd_His>/events.sql     data only
//   …
//
// Splitting the dump is what makes a restore selective: schema and data go in
// separately, so rolling one table back no longer means importing a file that
// recreates every other table too. It also means the operator chooses what comes
// back — `task_runs` is pure maintenance history that nothing in Phoenix reads,
// and `peers` is the ephemeral swarm index, so neither is worth restoring by
// default even though both are dumped (peers structure-only, via schema.sql).
//
// Each file is a separate mysqldump run, so each is a standalone, importable
// dump carrying its own preamble — the SET NAMES / TIME_ZONE / SQL_MODE lines
// that decide whether the data lands intact. Splitting one dump's output by hand
// would mean replicating that preamble per file, and getting the charset wrong
// is how a restore silently mangles torrent names.
//
// The trade is that each run is its own --single-transaction snapshot rather
// than all tables sharing one. Phoenix has no foreign keys and only one weak
// cross-table tie (torrents.downloads against the matching events rows), so the
// worst case is a counter off by a few over a dump that takes seconds; the
// alternative costs an output parser on every restore path.
//
// Credentials go to a private 0600 temp --defaults-extra-file so the password
// never reaches the process list, and every run is proc_open with an argv array
// (no shell).
//
// Needs the `mysqldump` binary, proc_open, and a writable backup_dir; the error
// string surfaces what was missing.

/**
 * @param PhoenixSettings $settings
 * @return array{ok: bool, file: string|null, error: string|null}
 */
function db_backup(array $settings, int $time): array
{
    require_once __DIR__.'/db.backup.remove.php';

    $backup_dir = ! empty($settings['backup_dir'])
        ? rtrim($settings['backup_dir'], '/').'/'
        : __DIR__.'/../../backups/';

    if (! is_dir($backup_dir)) {
        return ['ok' => false, 'file' => null, 'error' => 'BACKUP_DIR_NOT_FOUND'];
    }

    // zlib is effectively always present, but fall back to plain SQL rather
    // than failing the backup if this build lacks it.
    $compress = ! empty($settings['backup_compress']) && function_exists('gzopen');
    $suffix = '.sql'.($compress ? '.gz' : '');

    // Seconds, not just minutes: two runs in the same minute would otherwise
    // land in one directory and interleave their files.
    $dir = $backup_dir.$settings['db_name'].'.'.date('Ymd_His', $time);
    if (! @mkdir($dir, 0o755) && ! is_dir($dir)) {
        return ['ok' => false, 'file' => null, 'error' => 'Backup failed: could not create '.basename($dir).'.'];
    }

    // db_connect() mutates db_host in place (prepends 'p:' for persistent
    // connections); strip it before writing the credentials file.
    $db_host = (strncmp($settings['db_host'], 'p:', 2) === 0)
        ? substr($settings['db_host'], 2)
        : $settings['db_host'];

    $cnf_file = tempnam(sys_get_temp_dir(), 'phxbak_');
    if ($cnf_file === false) {
        @rmdir($dir);

        return ['ok' => false, 'file' => null, 'error' => 'Backup failed: could not create credentials file.'];
    }
    if (! chmod($cnf_file, 0o600)) {
        unlink($cnf_file);
        @rmdir($dir);

        return ['ok' => false, 'file' => null, 'error' => 'Backup failed: could not secure credentials file.'];
    }
    file_put_contents(
        $cnf_file,
        '[client]'.PHP_EOL.
        'host='.$db_host.PHP_EOL.
        'user='.$settings['db_user'].PHP_EOL.
        'password="'.str_replace('"', '""', $settings['db_pass']).'"'.PHP_EOL,
    );

    $base = [
        'mysqldump',
        '--defaults-extra-file='.$cnf_file,
        '--allow-keywords',
        '--replace',
        '--skip-add-drop-table',
        '--skip-lock-tables',
        '--single-transaction',
        '--tz-utc',
    ];

    // Mirrors db_create()'s list. `peers` is the ephemeral swarm index — it is
    // in schema.sql like every other table, but gets no data file.
    $data_tables = ['events', 'tasks', 'task_runs', 'torrents'];

    // schema.sql first, so a restore reading the directory in name order still
    // gets the tables before any data.
    $jobs = [
        'schema' => array_merge($base, ['--no-data', '--routines', '--triggers', $settings['db_name']]),
    ];
    foreach ($data_tables as $table) {
        $jobs[$table] = array_merge($base, ['--no-create-info', $settings['db_name'], $settings['db_prefix'].$table]);
    }

    $errfile = $dir.'/.err';
    $failure = null;
    foreach ($jobs as $name => $command) {
        $filepath = $dir.'/'.$name.$suffix;
        $out = $compress ? gzopen($filepath, 'wb1') : fopen($filepath, 'wb');
        if ($out === false) {
            $failure = 'Backup failed: could not open '.$name.$suffix.' for writing.';
            break;
        }

        // stdout is a pipe so it can be compressed on the way past; stderr stays
        // a file, so there is no second pipe to drain and no deadlock.
        $proc = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $errfile, 'a']],
            $pipes,
        );
        if ($proc === false) {
            $compress ? gzclose($out) : fclose($out);
            $failure = 'Backup failed: could not run mysqldump.';
            break;
        }
        fclose($pipes[0]);
        $write_failed = false;
        while (! feof($pipes[1])) {
            $chunk = fread($pipes[1], 262144);
            if ($chunk === false || $chunk === '') {
                break;
            }
            if (($compress ? gzwrite($out, $chunk) : fwrite($out, $chunk)) === false) {
                $write_failed = true;
                break;
            }
        }
        fclose($pipes[1]);
        $status = proc_close($proc);
        $compress ? gzclose($out) : fclose($out);

        if ($write_failed || $status !== 0) {
            $contents = is_readable($errfile) ? file_get_contents($errfile) : '';
            $error = is_string($contents) ? trim($contents) : '';
            $failure = 'Backup failed on '.$name.'.'.($error !== '' ? ' '.$error : '');
            break;
        }
    }

    @unlink($cnf_file);
    @unlink($errfile);

    if ($failure !== null) {
        // A partial backup is worse than none — it would rotate out a good one.
        db_backup_remove($dir);

        return ['ok' => false, 'file' => null, 'error' => $failure];
    }

    // Rotate: delete backups older than backup_retention days. Both the dated
    // directories and any legacy single-file dumps from before the split, so an
    // upgraded install still prunes what it wrote previously.
    if (intval($settings['backup_retention']) > 0) {
        $cutoff = $time - (intval($settings['backup_retention']) * 86400);
        $pattern = $backup_dir.$settings['db_name'].'.*';
        foreach (glob($pattern) ?: [] as $old) {
            if ($old === $dir) {
                continue;
            }
            if (! is_dir($old) && ! preg_match('/\.sql(\.gz)?$/', $old)) {
                continue;
            }
            $mtime = filemtime($old);
            if ($mtime === false || $mtime >= $cutoff) {
                continue;
            }
            db_backup_remove($old);
        }
    }

    return ['ok' => true, 'file' => $dir, 'error' => null];
}
