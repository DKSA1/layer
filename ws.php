<?php

require_once "./app/Autoloader.php";

$ws = \layer\core\http\websocket\WsServer::create('0.0.0.0',8080, 2048, 2);

if($ws)
    $ws->start();

?>