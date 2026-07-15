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

    $filepath = $backup_dir.$settings['db_name'].'.'.date('Ymd_Hi', $time).'.sql';

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

    // [ stdout file mode, argv ]. 'w' truncates for pass 1; 'a' appends the
    // peers structure for pass 2.
    $passes = [
        ['w', array_merge($base, ['--routines', '--triggers', '--ignore-table='.$settings['db_name'].'.'.$peers_table, $settings['db_name']])],
        ['a', array_merge($base, ['--no-data', $settings['db_name'], $peers_table])],
    ];

    $failed = false;
    foreach ($passes as [$mode, $command]) {
        $proc = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['file', $filepath, $mode],
                2 => ['file', $errfile, $mode],
            ],
            $pipes,
        );
        if ($proc === false) {
            $failed = true;
            break;
        }
        fclose($pipes[0]);
        if (proc_close($proc) !== 0) {
            $failed = true;
            break;
        }
    }

    @unlink($cnf_file);

    if ($failed) {
        $contents = is_readable($errfile) ? file_get_contents($errfile) : '';
        $error = is_string($contents) ? trim($contents) : '';
        @unlink($errfile);

        return ['ok' => false, 'file' => null, 'error' => 'Backup failed.'.($error !== '' ? ' '.$error : '')];
    }
    @unlink($errfile);

    // Rotate: delete backups older than backup_retention days.
    if (intval($settings['backup_retention']) > 0) {
        $cutoff = $time - (intval($settings['backup_retention']) * 86400);
        foreach (glob($backup_dir.$settings['db_name'].'.*.sql') ?: [] as $old) {
            $mtime = filemtime($old);
            if ($mtime === false || $mtime >= $cutoff) {
                continue;
            }
            @unlink($old);
        }
    }

    return ['ok' => true, 'file' => $filepath, 'error' => null];
}
