<?php

declare(strict_types=1);

////	view_admin_tasks_html
// Render the admin Task History page: every recorded maintenance run, newest
// first, with a task filter and a pager. Columns: Task, Run at, Triggered by.
//
// "Triggered by" is the column worth reading. `cron` means the scheduled job
// fired; `auto` means clean_with_cron is off and an announce paid for the
// cleanup inline; `admin` means someone pressed a button. A history of `auto`
// where cron was expected is the signal that the crontab entry is not running.
// Those two are the expected background noise and stay plain; anything else is
// coloured, so a manual intervention stands out in a page of scheduled runs.
//
// There is no status column because there is nothing to put in it: task_log()
// is called only after a task succeeds, at every one of its call sites, so a
// failed run writes no row at all and shows here as a gap rather than a
// failure. Do not read this page as "everything is fine".
//
// Filtering is a GET form, not a client-side filter — the table is paged, so
// filtering in the browser would only ever search the rendered page. The
// trigger filter is the useful half: "prune, triggered by announce" answers
// whether the cron entry is running, which the unfiltered list buries.
// `auto` is labelled Announce here — the stored value says where it came from,
// the label says what a reader needs to know. Wrapped in
// the shared admin layout. Returns HTML string.

/**
 * @param PhoenixSettings $settings
 * @param list<array{id: int, name: string, value: int, source: string}> $runs
 */
function view_admin_tasks_html(array $settings, array $runs, int $total, int $offset, int $limit, string $name, string $source, string $csrf_token): string
{
    require_once __DIR__.'/html.admin.layout.php';

    $labels = [
        'install' => ['wand-2', 'Installed'],
        'migrate' => ['git-merge', 'Migrated'],
        'clean' => ['brush-cleaning', 'Pruned'],
        'analyze' => ['gauge', 'Analyzed'],
        'optimize' => ['chart-no-axes-column', 'Optimized'],
        'check' => ['shield-check', 'Checked'],
        'backup' => ['archive', 'Backed up'],
    ];

    // Scheduled and announce-time runs are the expected background noise, so
    // they stay plain. Anything else is worth catching the eye: `admin` means a
    // person intervened, and an unrecognised source means a row this Phoenix
    // did not write.
    $source_badge = static function (string $source): string {
        if ($source === '') {
            return '<span class="dim">&mdash;</span>';
        }
        $class = match ($source) {
            'cron', 'auto' => 'badge',
            'admin' => 'badge badge-blue',
            default => 'badge badge-yellow',
        };

        return '<span class="'.$class.'">'.htmlspecialchars(ucfirst($source), ENT_QUOTES, 'UTF-8').'</span>';
    };

    $query = static function (array $overrides) use ($name, $source): string {
        $params = array_merge(['page' => 'tasks', 'name' => $name, 'source' => $source], $overrides);
        $params = array_filter($params, static fn (mixed $v): bool => $v !== '' && $v !== null);

        return '?'.htmlspecialchars(http_build_query($params), ENT_QUOTES, 'UTF-8');
    };

    $body = '';

    // Filter bar.
    $options = '<option value=""'.($name === '' ? ' selected' : '').'>All tasks</option>';
    foreach ($labels as $key => [, $label]) {
        $options .= '<option value="'.$key.'"'.($name === $key ? ' selected' : '').'>'.$label.'</option>';
    }
    $source_options = '<option value=""'.($source === '' ? ' selected' : '').'>Any trigger</option>';
    foreach (['cron' => 'Cron', 'auto' => 'Announce', 'admin' => 'Admin'] as $key => $label) {
        $source_options .= '<option value="'.$key.'"'.($source === $key ? ' selected' : '').'>'.$label.'</option>';
    }

    $body .= '<form method="GET" class="ph-toolbar">'.
        '<input type="hidden" name="page" value="tasks">'.
        '<select name="name" class="ph-select">'.$options.'</select>'.
        '<select name="source" class="ph-select">'.$source_options.'</select>'.
        '<button type="submit" class="btn btn-secondary btn-sm">Filter</button>'.
        ($name !== '' || $source !== '' ? '<a class="btn btn-ghost btn-sm" href="?page=tasks">Clear</a>' : '').
        '<span class="ph-toolbar-count muted">'.number_format($total).' run'.($total === 1 ? '' : 's').'</span>'.
        '</form>';

    if ($runs === []) {
        $body .= '<div class="ph-empty"><span class="ph-ico" data-lucide="history"></span><p>'.
            ($total === 0 && $name === '' && $source === ''
                ? 'No maintenance has run yet. Tasks are recorded as cron, the announce-time fallback, or the Utilities page runs them.'
                : 'No runs match this filter.').
            '</p></div>';
    } else {
        $rows = '';
        foreach ($runs as $run) {
            [$icon, $label] = $labels[$run['name']] ?? ['circle-dot', ucfirst($run['name'])];
            $by = $source_badge($run['source']);
            $rows .= '<tr>'.
                '<td><span class="flex items-center gap-2"><span class="ph-ico ph-li-ico" data-lucide="'.$icon.'"></span>'.htmlspecialchars($label).'</span></td>'.
                '<td class="mono muted" data-sort="'.$run['value'].'">'.date('Y-m-d H:i:s', $run['value']).'</td>'.
                '<td>'.$by.'</td>'.
                '</tr>';
        }
        $body .= '<div class="ph-card-table">'.
            '<table><thead><tr><th>Task</th><th>Run at</th><th>Triggered by</th></tr></thead>'.
            '<tbody>'.$rows.'</tbody></table></div>';
    }

    $last = $offset + count($runs);
    if ($offset > 0 || $last < $total) {
        $prev = $offset > 0
            ? '<a class="btn btn-ghost btn-sm" href="'.$query(['offset' => max(0, $offset - $limit)]).'"><span class="ph-ico" data-lucide="arrow-left"></span>Previous</a>'
            : '<span class="btn btn-ghost btn-sm" aria-disabled="true"><span class="ph-ico" data-lucide="arrow-left"></span>Previous</span>';
        $next = $last < $total
            ? '<a class="btn btn-ghost btn-sm" href="'.$query(['offset' => $offset + $limit]).'">Next<span class="ph-ico" data-lucide="arrow-right"></span></a>'
            : '<span class="btn btn-ghost btn-sm" aria-disabled="true">Next<span class="ph-ico" data-lucide="arrow-right"></span></span>';
        $body .= '<div class="flex items-center gap-2 justify-end mt-4">'.$prev.$next.'</div>';
    }

    // History is pruned by task_retention; say so rather than let a short
    // window read as "maintenance stopped running".
    $retention = intval($settings['task_retention']);
    $body .= '<p class="muted text-sm mt-4">'.
        ($retention > 0
            ? 'History is pruned to the last '.$retention.' day'.($retention === 1 ? '' : 's').' by <code>task_retention</code>.'
            : 'Every run is kept &mdash; <code>task_retention</code> is <code>0</code>.').
        '</p>';

    $actions = '<a class="btn btn-ghost btn-sm" href="?page=utilities"><span class="ph-ico" data-lucide="wrench"></span>Run tasks</a>';

    return view_admin_layout_html($settings, 'Task History', $body, 'tasks', $csrf_token, 'Server', $actions, 'narrow');
}
