<?php

declare(strict_types=1);

////	db_size
// Returns aggregated storage statistics for the connection's configured
// database, suitable for the admin panel's "Database Size" widget. Keys:
// Data, Indexes, Total, Free (each an int byte count). Returns false when
// the query fails or no rows match (e.g. an empty schema).

function db_size(mysqli $connection, array $settings): array|false
{
    $result = mysqli_query(
        $connection,
        'SELECT '.
            '`data_length`  AS `Data`, '.
            '`index_length` AS `Indexes`, '.
            'SUM(`data_length` + `index_length`) AS `Total`, '.
            'SUM(`data_free`) AS `Free` '.
        'FROM `information_schema`.`TABLES` '.
        'WHERE `table_schema` = \''.$settings['db_name'].'\' '.
        'GROUP BY `table_schema`;',
        MYSQLI_STORE_RESULT,
    );
    if (! $result) {
        return false;
    }
    $row = mysqli_fetch_assoc($result);
    if (! $row) {
        return false;
    }

    return $row;
}
