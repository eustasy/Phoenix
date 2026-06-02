<?php

declare(strict_types=1);

////	admin_install_controller
//  Handles first-run installer mode when no config file exists.
//  Returns HTML output.

require_once __DIR__.'/../views/html.install.php';

function admin_install_controller(string $config_path)
{
    error_reporting(0);

    require_once __DIR__.'/../functions/install.sanitize.post.php';
    $values = install_sanitize_post($_POST);

    $settings_writable = is_writable(dirname($config_path));
    $install_error = null;

    ////	Prepare form values (repopulate after failed attempt)
    $form = [
        'db_host' => $values['db_host'],
        'db_user' => $values['db_user'],
        'db_name' => $values['db_name'],
        'db_prefix' => $values['db_prefix'] !== '' ? $values['db_prefix'] : 'phoenix_',
        'db_persist' => ! empty($_POST) ? $values['db_persist'] : true,
        'open_tracker' => $values['open_tracker'],
        'public_index' => $values['public_index'],
    ];


    ////	Process installation
    // 'process' is part of the request, not the sanitised config payload, so it
    // is read from $_POST directly. Without this, install_sanitize_post() never
    // sets it and the controller could never reach the install branch.
    if (($_POST['process'] ?? '') !== 'install') {
        return view_install_html($settings_writable, $install_error, $form);
    }

    if (! $settings_writable) {
        $install_error = 'The <code>config/</code> directory is not writable. Please make it writable and try again.';

        return view_install_html($settings_writable, $install_error, $form);
    }

    ////	Test DB connection before writing config
    $test_host = $values['db_persist'] ? 'p:' : '';
    $test_host .= $values['db_host'];
    try {
        $test_conn = @mysqli_connect($test_host, $values['db_user'], $values['db_pass'], $values['db_name']);
    } catch (mysqli_sql_exception $e) {
        $test_conn = false;
    }

    if (! $test_conn) {
        $install_error = 'Could not connect to the database: '.mysqli_connect_error();

        return view_install_html($settings_writable, $install_error, $form);
    }

    ////	Create tables
    require_once __DIR__.'/../model/db.create.php';
    if (! db_create($test_conn, $values)) {
        $install_error = 'Connected, but could not create the tables.';

        return view_install_html($settings_writable, $install_error, $form);
    }

    ////	Write config file
    require_once __DIR__.'/../functions/install.build.config.php';
    if (file_put_contents($config_path, install_build_config($values)) === false) {
        $install_error = 'Connected and created tables, but could not write the configuration file. Check that <code>config/</code> is writable.';

        return view_install_html($settings_writable, $install_error, $form);
    }

    mysqli_close($test_conn);
    header('Location: admin.php?installed=1');
    exit;
}
