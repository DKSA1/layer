<?php
require_once "./app/Autoloader.php";
// TODO : give configuration path
$app = \layer\core\App::getInstance();
$app->setAppErrorCallback(function($error) {
    \layer\core\utils\Logger::write("[ERROR] Framework error");
});
$app->setAppFinallyCallback(function(\layer\core\http\Response $response) {
    $d = new DateTime();
    $d->setTimestamp($response->getResponseTime());
    \layer\core\utils\Logger::write("[".$response->getResponseCode()."] serving content in ".$d->format('s.u')." ms");
});
$app->handleRequest();
?>