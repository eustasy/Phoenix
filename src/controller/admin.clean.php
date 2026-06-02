<?php

declare(strict_types=1);

////	admin_clean_action
//  Handles peers list cleanup action.
//  Returns message string on completion.

function admin_clean_action($connection, $settings, $time)
{
    require_once __DIR__.'/../functions/task.clean.php';

    if (task_clean($connection, $settings, $time)) {
        return 'The peers list has been cleaned.';
    } else {
        return 'Could not clean the peers list.';
    }
}
