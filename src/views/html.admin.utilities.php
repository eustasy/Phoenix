<?php

declare(strict_types=1);

////	view_admin_utilities_html
// Render the admin Utilities page: the database setup/reset action plus the
// clean, optimize, and schema-migrate maintenance actions. The setup action
// also appears when the tables are missing (so the operator can install); the
// clean/optimize/migrate actions need live tables. Any action message is shown
// above. Wrapped in the shared admin layout (narrow). Returns HTML string.
//
// Parameters:
//   $settings - settings array (uses db_reset)
//   $tables_installed - bool, whether all tables are installed
//   $message - string|false, optional action-result message to display
//   $csrf_token - string, per-session token embedded in every form

/** @param PhoenixSettings $settings */
function view_admin_utilities_html(array $settings, bool $tables_installed, string|false $message, string $csrf_token): string
{
    require_once __DIR__.'/html.admin.layout.php';

    // Hidden field carrying the CSRF token, embedded in every state-changing
    // form. Escaped defensively even though the token is always hex.
    $csrf_field = '<input type="hidden" name="csrf" value="'.htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8').'">';

    // An action row: a description on the left, a single-button form on the
    // right. class="mysql" hooks the layout's double-submit guard.
    $action = static function (string $title, string $desc, string $process, string $label, string $csrf_field): string {
        return '<tr><td><strong>'.$title.'</strong><div class="dim text-sm">'.$desc.'</div></td>'.
            '<td class="tar"><form class="mysql" action="?page=utilities" method="POST">'.
            '<input type="hidden" name="process" value="'.$process.'">'.$csrf_field.
            '<button type="submit" name="submit" class="btn btn-secondary btn-sm">'.$label.'</button></form></td></tr>';
    };

    $body = '';

    if ($message) {
        $body .= '<div class="alert alert-info"><span class="ph-ico" data-lucide="info"></span><div>'.htmlspecialchars($message).'</div></div>';
    }

    $rows = '';

    // Setup/Reset action. Available when resets are enabled, or whenever the
    // tables are missing (so a fresh install can proceed).
    if ($settings['db_reset'] || ! $tables_installed) {
        $body .= '<div class="alert alert-warning"><span class="ph-ico" data-lucide="triangle-alert"></span><div>Set <code>$settings[\'db_reset\']</code> to false to disable resets, or delete <code>public/admin.php</code> once you\'re up and running.</div></div>';
        $rows .= $action('Setup', 'Install, upgrade, or reset the database', 'setup', 'Setup', $csrf_field);
    } else {
        $rows .= '<tr><td><strong>Setup</strong><div class="dim text-sm">Install, upgrade, or reset the database</div></td>'.
            '<td class="tar"><span class="badge">Disabled</span></td></tr>';
    }

    // Clean, Optimize, and Migrate (only with installed tables).
    if ($tables_installed) {
        $rows .= $action('Clean', 'Remove redundant / stale peers', 'clean', 'Clean peers', $csrf_field);
        $rows .= $action('Optimize', 'Check, analyze, repair &amp; optimize tables', 'optimize', 'Optimize', $csrf_field);
        $rows .= $action('Upgrade schema', 'Apply idempotent migrations', 'migrate', 'Upgrade', $csrf_field);
    }

    $body .= '<div class="ph-card-table"><table><tbody>'.$rows.'</tbody></table></div>';

    return view_admin_layout_html($settings, 'Utilities', $body, 'utilities', $csrf_token, 'Server', '', true);
}
