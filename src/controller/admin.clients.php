<?php

declare(strict_types=1);

////	admin_clients_controller
// Renders the admin Clients page: which BitTorrent clients are on the tracker,
// as a chart and a table, with a metric toggle between the live swarm and the
// events ledger's history. Read-only. Dispatched by admin_panel_controller()
// for page=clients.
//
// The two sources answer different questions and are not interchangeable:
//   * live   — the clients currently in the swarm, derived from peer_id per
//              request and never stored, broken down by version.
//   * events — every completed download ever logged, by client FAMILY only.
//              The ledger stores whatever label was written at the time, so a
//              tracker running a while carries both "Transmission" and
//              "Transmission 4.1.3.0"; the model folds them together, which is
//              why history has no version axis.
//
// Only the selected metric is computed — the ledger aggregation reads the whole
// events table, and there is no reason to pay for it to render the live view.

/** @param PhoenixSettings $settings */
function admin_clients_controller(mysqli $connection, array $settings): string
{
    require_once __DIR__.'/../model/db.tables.installed.php';
    $tables_installed = db_tables_installed($connection, $settings);

    $metric = ($_GET['metric'] ?? '') === 'events' ? 'events' : 'live';

    $families = [];
    $total = 0;
    if ($tables_installed) {
        if ($metric === 'events') {
            require_once __DIR__.'/../model/events.client.counts.php';
            // One '' version per family: the chart draws a solid bar, since the
            // ledger cannot supply a version breakdown across its whole history.
            foreach (events_client_counts($connection, $settings) as $family => $count) {
                $families[$family] = ['' => $count];
                $total += $count;
            }
        } else {
            require_once __DIR__.'/../model/peers.client.breakdown.php';
            // Every family, not the dashboard's top seven — this is the page
            // that exists to show the whole distribution.
            $families = peers_client_breakdown($connection, $settings, 1000);
            foreach ($families as $versions) {
                $total += array_sum($versions);
            }
        }
    }

    require_once __DIR__.'/../functions/auth.csrf.token.php';
    $csrf_token = ! empty($settings['admin_password']) ? auth_csrf_token() : '';

    require_once __DIR__.'/../views/html.admin.clients.php';

    return view_admin_clients_html($settings, $metric, $families, $total, $csrf_token);
}
