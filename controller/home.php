<?php
$this->respond('GET', '/?', function ($request, $response, $service, $app) {
    $mysqli = $app->db;
    $service->render('view/home.phtml');
});