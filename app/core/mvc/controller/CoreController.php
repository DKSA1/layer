<?php

namespace layer\core\mvc\controller;

use layer\core\exception\ForwardException;
use layer\core\http\HttpHeaders;
use layer\core\http\Request;
use layer\core\http\Response;
use layer\core\utils\Logger;

abstract class CoreController
{
    /**
     * @var Request $request
     */
    protected $request;
    /**
     * @var Response $response
     */
    protected $response;

    /**
     * @param string $internalUrl
     * @param int $httpCode
     * @throws ForwardException
     */
    protected final function forward($internalUrl, $httpCode = HttpHeaders::MovedTemporarily){
        Logger::write("[".$httpCode."] Forwarding request to new location: ".$internalUrl);
        throw new ForwardException($httpCode, $internalUrl);
    }

    protected final function redirect($url, $timeout = 0) {
        Logger::write(' Redirecting to '.$url);
        header( "refresh:".$timeout.";url=".$url);
        $this->response->sendHeaders();
        exit();
    }
}