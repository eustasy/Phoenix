<?php

declare(strict_types=1);

////	db_backup
// Dump the database to a timestamped .sql file in backup_dir, then rotate out
// dumps older than backup_retention days. The engine for both bin/backup-database.php
// (cron) and the admin Backups page, so it returns a result array rather than
// echoing/exiting:
//   ['ok' => bool, 'file' => ?string (path on success), 'error' => ?string]
//
// Behaviour mirrors the original cron script exactly: strip the 'p:' persistent
// prefix db_connect() prepends to db_host; write credentials to a private 0600
// temp --defaults-extra-file so the password never hits the process list; run
// two mysqldump passes via proc_open with an argv array (no shell) — pass 1
// dumps everything except the ephemeral peers rows (data + routines/triggers),
// pass 2 appends the peers table structure only; then rotate by mtime.
//
// With backup_compress on (the default) the dump is gzipped as it streams, to
// <name>.sql.gz. Both passes are read from a pipe and written through one zlib
// handle, so the plain SQL is never written to disk — peak disk is the
// compressed size, which matters more on a small host than the CPU does.
// Compression is PHP's zlib, so no external binary is needed; a build without
// it silently writes plain .sql. Level 1 is deliberate: a SQL dump reaches
// roughly 10x there, and the higher levels cost several times the CPU for a few
// percent more.
//
// Needs the `mysqldump` binary, proc_open, and a writable backup_dir; the error
// string surfaces what was missing.

/**
 * @param PhoenixSettings $settings
 * @return array{ok: bool, file: string|null, error: string|null}
 */
function db_backup(array $settings, int $time): array
{
    $backup_dir = ! empty($settings['backup_dir'])
        ? rtrim($settings['backup_dir'], '/').'/'
        : __DIR__.'/../../backups/';

    if (! is_dir($backup_dir)) {
        return ['ok' => false, 'file' => null, 'error' => 'BACKUP_DIR_NOT_FOUND'];
    }

    // zlib is effectively always present, but fall back to plain SQL rather
    // than failing the backup if this build lacks it.
    $compress = ! empty($settings['backup_compress']) && function_exists('gzopen');
    $filepath = $backup_dir.$settings['db_name'].'.'.date('Ymd_Hi', $time).'.sql'.($compress ? '.gz' : '');

    // db_connect() mutates db_host in place (prepends 'p:' for persistent
    // connections); strip it before writing the credentials file.
    $db_host = (strncmp($settings['db_host'], 'p:', 2) === 0)
        ? substr($settings['db_host'], 2)
        : $settings['db_host'];

    $cnf_file = tempnam(sys_get_temp_dir(), 'phxbak_');
    if ($cnf_file === false) {
        return ['ok' => false, 'file' => null, 'error' => 'Backup failed: could not create credentials file.'];
    }
    if (! chmod($cnf_file, 0o600)) {
        unlink($cnf_file);

        return ['ok' => false, 'file' => null, 'error' => 'Backup failed: could not secure credentials file.'];
    }
    file_put_contents(
        $cnf_file,
        '[client]'.PHP_EOL.
        'host='.$db_host.PHP_EOL.
        'user='.$settings['db_user'].PHP_EOL.
        'password="'.str_replace('"', '""', $settings['db_pass']).'"'.PHP_EOL,
    );

    $errfile = $filepath.'.err';
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
    $peers_table = $settings['db_prefix'].'peers';

    $passes = [
        array_merge($base, ['--routines', '--triggers', '--ignore-table='.$settings['db_name'].'.'.$peers_table, $settings['db_name']]),
        array_merge($base, ['--no-data', $settings['db_name'], $peers_table]),
    ];

    // One output handle for both passes — pass 2 appends to pass 1's stream,
    // which a gzip member cannot do after the fact.
    $out = $compress ? gzopen($filepath, 'wb1') : fopen($filepath, 'wb');
    if ($out === false) {
        @unlink($cnf_file);

        return ['ok' => false, 'file' => null, 'error' => 'Backup failed: could not open '.basename($filepath).' for writing.'];
    }

    $failed = false;
    foreach ($passes as $command) {
        // stdout is a pipe so it can be compressed on the way past; stderr stays
        // a file, so there is no second pipe to drain and no deadlock.
        $proc = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['file', $errfile, 'a'],
            ],
            $pipes,
        );
        if ($proc === false) {
            $failed = true;
            break;
        }
        fclose($pipes[0]);
        while (! feof($pipes[1])) {
            $chunk = fread($pipes[1], 262144);
            if ($chunk === false || $chunk === '') {
                break;
            }
            if (($compress ? gzwrite($out, $chunk) : fwrite($out, $chunk)) === false) {
                $failed = true;
                break 2;
            }
        }
        fclose($pipes[1]);
        if (proc_close($proc) !== 0) {
            $failed = true;
            break;
        }
    }

    if ($compress) {
        gzclose($out);
    } else {
        fclose($out);
    }
    @unlink($cnf_file);

    if ($failed) {
        $contents = is_readable($errfile) ? file_get_contents($errfile) : '';
        $error = is_string($contents) ? trim($contents) : '';
        @unlink($errfile);
        // A partial dump is worse than none — it would rotate out a good one.
        @unlink($filepath);

        return ['ok' => false, 'file' => null, 'error' => 'Backup failed.'.($error !== '' ? ' '.$error : '')];
    }
    @unlink($errfile);

    // Rotate: delete backups older than backup_retention days.
    if (intval($settings['backup_retention']) > 0) {
        $cutoff = $time - (intval($settings['backup_retention']) * 86400);
        // Both extensions, so dumps written before compression was enabled (or
        // while it was off) still rotate out.
        $pattern = $backup_dir.$settings['db_name'].'.*.sql';
        $old_files = array_merge(glob($pattern) ?: [], glob($pattern.'.gz') ?: []);
        foreach ($old_files as $old) {
            $mtime = filemtime($old);
            if ($mtime === false || $mtime >= $cutoff) {
                continue;
            }
            @unlink($old);
        }
    }

    return ['ok' => true, 'file' => $filepath, 'error' => null];
}
