<?php
date_default_timezone_set('UTC');
header("Content-Type: text/html; charset=UTF-8");
mb_internal_encoding("UTF-8");

// https://github.com/chriso/klein.php/issues/176
$base  = dirname($_SERVER['PHP_SELF']);
if (ltrim($base, '/')) $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], strlen($base));


// Include composer
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

$klein = new \Klein\Klein();

$klein->respond(function ($request, $response, $service, $app) use ($klein) {
    $app->register('db', function() {
        // Connect to database
        global $config;
        return new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name'], $config['db_port']);
    });
    global $base;
    $service->base_url = $base;
});


$klein->with("/daily_summary", "controller/daily_summary.php");
$klein->with("", "controller/home.php");

$klein->dispatch();
