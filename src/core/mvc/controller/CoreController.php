<?php

namespace rloris\layer\core\mvc\controller;

use rloris\layer\core\http\Request;
use rloris\layer\core\http\Response;
use rloris\layer\core\manager\CorsManager;
use rloris\layer\core\manager\FilterManager;
use rloris\layer\core\manager\SessionManager;

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
     * @var mixed
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