<?php

namespace layer\core\mvc\controller;

use layer\core\error\EForward;
use layer\core\http\HttpHeaders;
use layer\core\http\IHttpHeaders;
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

    public function __construct()
    {
        $this->request = Request::getInstance();
        $this->response = Response::getInstance();
    }

    protected final function forward($internalUrl, $httpCode = HttpHeaders::MovedTemporarily)
    {
        Logger::write("[".$httpCode."] Forwarding request to new location: ".$internalUrl);
        throw new EForward($httpCode, $internalUrl);
    }

    protected final function redirect($url, $timeout = 0)
    {
        Logger::write(' Redirecting to '.$url);
        $this->response->putHeader(IHttpHeaders::Refresh,$timeout.";url=".$url);
        $this->response->sendHeaders();
        exit();
    }
}