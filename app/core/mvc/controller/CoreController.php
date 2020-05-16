<?php

namespace layer\core\mvc\controller;

use layer\core\error\EForward;
use layer\core\error\ERedirect;
use layer\core\http\HttpHeaders;
use layer\core\http\IHttpCodes;
use layer\core\http\IHttpHeaders;
use layer\core\http\Request;
use layer\core\http\Response;
use layer\core\utils\Logger;

abstract class CoreController
{
    /**
     * @var Request $request
     */
    protected static $request;
    /**
     * @var Response $response
     */
    protected static $response;

    public function __construct()
    {
    }

    protected final function forward($internalUrl)
    {
        Logger::write("Forwarding request to new location: ".$internalUrl);
        throw new EForward(trim($internalUrl,'/'));
    }

    protected final function redirect($location, $httpCode = IHttpCodes::MovedTemporarily)
    {
        Logger::write('Redirecting to '.$location);
        throw new ERedirect($location, $httpCode);
    }
}