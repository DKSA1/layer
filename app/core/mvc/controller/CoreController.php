<?php

namespace layer\core\mvc\controller;

use layer\core\error\EForwardURL;
use layer\core\error\ERedirect;
use layer\core\http\HttpHeaders;
use layer\core\http\IHttpCodes;
use layer\core\http\IHttpHeaders;
use layer\core\http\Request;
use layer\core\http\Response;
use layer\core\manager\CorsManager;
use layer\core\manager\FilterManager;
use layer\core\manager\SessionManager;
use layer\core\utils\LogManager;

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
    /**
     * @var FilterManager
     */
    protected static $filterManager;
    /**
     * @var
     */
    protected static $data;
    protected static $shared;

    protected final function session(): SessionManager {
        return SessionManager::getInstance();
    }

    protected final function cors() : CorsManager {
        return CorsManager::getInstance(self::$request, self::$response);
    }

}