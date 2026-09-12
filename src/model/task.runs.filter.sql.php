<?php

declare(strict_types=1);

////	task_runs_filter_sql
// Build the shared WHERE clause behind the admin Task History listing, so
// task_runs_select() and task_runs_count() filter identically — a mismatch
// would page a filtered list against an unfiltered total and silently produce
// empty trailing pages.
//
// $name narrows to one maintenance task ('clean', 'optimize', 'backup',
// 'migrate', 'install'); $source to one trigger ('cron', 'auto', 'admin').
// Both are '' for "any". The controller checks each against the values Phoenix
// actually writes before calling, but they are bound as parameters regardless:
// they arrive from the query string, and validation upstream is not a reason to
// interpolate downstream.
//
// Returns ['where' => string (empty or leading " WHERE "), 'params' => list].

/** @return array{where: string, params: list<string>} */
function task_runs_filter_sql(string $name = '', string $source = ''): array
{
    $clauses = [];
    $params = [];

    if ($name !== '') {
        $clauses[] = '`name` = ?';
        $params[] = $name;
    }

    if ($source !== '') {
        $clauses[] = '`source` = ?';
        $params[] = $source;
    }

    return [
        'where' => $clauses === [] ? '' : ' WHERE '.implode(' AND ', $clauses),
        'params' => $params,
    ];
}
