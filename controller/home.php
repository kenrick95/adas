<?php
$this->respond('GET', '', function ($request, $response, $service, $app) {
    return $response->redirect($service->base_url . "/");
});
$this->respond('GET', '/', function ($request, $response, $service, $app) {
    $service->render('view/home.phtml');
});