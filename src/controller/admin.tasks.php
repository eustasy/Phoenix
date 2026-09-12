<?php

declare(strict_types=1);

////	admin_tasks_controller
// Renders the admin Task History page: the maintenance runs recorded in
// `task_runs`, newest first, paged and filterable by task name. The dashboard's
// Maintenance block shows only the last run of each task (from `tasks`); this
// is the full log behind it — how often cron is actually firing, and whether
// runs are coming from cron or falling back to announce-time cleanup.
//
// Read-only: there is no action to CSRF-protect, so the token is only taken for
// the layout's logout form. Dispatched by admin_panel_controller() for
// page=tasks.

/** @param PhoenixSettings $settings */
function admin_tasks_controller(mysqli $connection, array $settings): string
{
    require_once __DIR__.'/../model/task.runs.select.php';
    require_once __DIR__.'/../model/task.runs.count.php';

    // Only the values Phoenix writes; anything else widens back to "any"
    // rather than reaching the query as an unknown value and matching nothing.
    $name = isset($_GET['name']) && is_string($_GET['name']) ? $_GET['name'] : '';
    if (! in_array($name, ['install', 'migrate', 'clean', 'optimize', 'backup'], true)) {
        $name = '';
    }

    $source = isset($_GET['source']) && is_string($_GET['source']) ? $_GET['source'] : '';
    if (! in_array($source, ['cron', 'auto', 'admin'], true)) {
        $source = '';
    }

    $limit = max(1, intval($settings['admin_tasks_limit']));
    $offset = isset($_GET['offset']) ? max(0, intval($_GET['offset'])) : 0;

    $runs = task_runs_select($connection, $settings, $name, $source, $limit, $offset);
    $total = task_runs_count($connection, $settings, $name, $source);

    require_once __DIR__.'/../functions/auth.csrf.token.php';
    $csrf_token = ! empty($settings['admin_password']) ? auth_csrf_token() : '';

    require_once __DIR__.'/../views/html.admin.tasks.php';

    return view_admin_tasks_html($settings, $runs, $total, $offset, $limit, $name, $source, $csrf_token);
}
