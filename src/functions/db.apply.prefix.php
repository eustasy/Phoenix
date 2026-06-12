<?php

declare(strict_types=1);

////	db_apply_prefix
// Rewrites the literal default prefix `phoenix_` in a SQL string to the
// install's actual prefix. If the prefix is already `phoenix_` the string is
// returned unchanged so no spurious allocations occur on standard installs.
function db_apply_prefix(string $sql, string $prefix): string
{
    if ($prefix === 'phoenix_') {
        return $sql;
    }

    return str_replace('phoenix_', $prefix, $sql);
}
