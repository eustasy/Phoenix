<?php

declare(strict_types=1);

////	admin_backups_controller
// Renders the admin Backups page: a "Run backup now" button and a list of the
// existing dumps. Verifies the CSRF token on the backup POST (process=backup)
// before running it, then lists the backups via the shared layout. Dispatched
// by admin_panel_controller() for page=backups.

/** @param PhoenixSettings $settings */
function admin_backups_controller(mysqli $connection, array $settings, int $time): string
{
    require_once __DIR__.'/../functions/auth.csrf.token.php';
    require_once __DIR__.'/../functions/auth.csrf.verify.php';

    // CSRF only matters when a password (hence a session) is in play; mirrors
    // admin_dashboard_page / admin_torrents_controller.
    $csrf_enabled = ! empty($settings['admin_password']);

    $process = '';
    if (! empty($_POST['process'])) {
        $process = htmlentities($_POST['process'], ENT_QUOTES, 'UTF-8');
    }

    $message = false;

    // Downloads are GET reads — no CSRF needed (the response isn't cross-origin
    // readable), and the name is validated strictly against the backup list so
    // there is no path traversal risk.
    if (isset($_GET['download'])) {
        require_once __DIR__.'/../functions/db.backup.path.php';
        $name = is_string($_GET['download']) ? $_GET['download'] : '';
        $path = db_backup_path($settings, $name);
        if ($path === false) {
            $message = 'Backup not found.';
        } else {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.basename($path).'"');
            $size = filesize($path);
            if ($size !== false) {
                header('Content-Length: '.$size);
            }
            readfile($path);
            exit;
        }
    }

    if ($process !== '' && $csrf_enabled && ! auth_csrf_verify()) {
        $message = 'Security check failed. Please reload the page and try again.';
        $process = '';
    }

    if ($process === 'backup') {
        require_once __DIR__.'/admin.backup.php';
        $message = admin_backup_action($connection, $settings, $time);
    }

    require_once __DIR__.'/../functions/db.backup.list.php';
    $backups = db_backup_list($settings);

    $csrf_token = $csrf_enabled ? auth_csrf_token() : '';

    require_once __DIR__.'/../views/html.admin.backups.php';

    return view_admin_backups_html($settings, $backups, $message, $csrf_token);
}
