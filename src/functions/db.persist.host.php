<?php

declare(strict_types=1);

////	db_persist_host
// Returns the host string with a 'p:' prefix when persistent connections are
// requested, otherwise unchanged. mysqli_connect() interprets a leading 'p:'
// as opt-in to a persistent connection. This prefix is sticky on the stored
// db_host value, so any code that reads db_host outside mysqli_connect (e.g.
// bin/backup-database.php) must strip it before use.
function db_persist_host(string $host, bool $persist): string {
	return $persist ? 'p:'.$host : $host;
}
