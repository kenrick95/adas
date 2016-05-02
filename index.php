<?php
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
        global $db_host;
        global $db_user;
        global $db_pass;
        global $db_name;
        global $db_port;
        return new mysqli($db_host, $db_user, $db_pass, $db_name); # TODO: somehow this failed
    });
});

$klein->with("/daily_summary", "controller/daily_summary.php");
$klein->with("", "controller/home.php");
$klein->respond(function () {
    return 'All the things';
});

$klein->dispatch();