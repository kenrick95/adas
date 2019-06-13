<?php
date_default_timezone_set('UTC');
header("Content-Type: text/html; charset=UTF-8");
mb_internal_encoding("UTF-8");


if (php_sapi_name() !== 'cli-server') {
    // https://github.com/chriso/klein.php/issues/176
    $base  = dirname($_SERVER['PHP_SELF']);
    if (ltrim($base, '/')) $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], strlen($base));
} else {
    $base = '/';
}


// Include composer
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

$klein = new \Klein\Klein();

$klein->respond(function ($request, $response, $service, $app) use ($klein) {
    $app->register('db', function () {
        return function ($db_name) {
            global $config;
            if (empty($db_name)) {
                $db_name = $config['db_name'];
            }
            return new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $db_name, $config['db_port']);
        };
    });
    global $base;
    $service->base_url = $base;
    $service->http_request = function ($url, $post = "", $header = "", &$ch = null) {
        global $config;
        if (!$ch) {
            $ch = curl_init();
        }

        //Change the user agent below suitably
        if (!empty($post)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array($header));
        }
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, $config['utils_userAgent']);
        curl_setopt($ch, CURLOPT_URL, ($url));
        curl_setopt($ch, CURLOPT_ENCODING, "UTF-8");
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $config['utils_cookieFile']);
        curl_setopt($ch, CURLOPT_COOKIEJAR,  $config['utils_cookieFile']);
        if (!empty($post)) {
            curl_setopt($ch, CURLOPT_POST, true);
            if (is_array($post)) {
                $post = http_build_query($post);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        // UNCOMMENT TO DEBUG TO output.tmp
        // curl_setopt($ch, CURLOPT_VERBOSE, true); // Display communication with server
        // $fp = fopen("output.tmp", "w");
        // curl_setopt($ch, CURLOPT_STDERR, $fp); // Display communication with server

        $return = curl_exec($ch);
        if (!$return) {
            throw new Exception("Error getting data from server ($url): " . curl_error($ch));
        }

        curl_close($ch);

        return $return;
    };
});


$klein->with("/daily_article", "controller/daily_article.php");
// TODO: monthy_summary
$klein->with("", "controller/home.php");

$klein->dispatch();
