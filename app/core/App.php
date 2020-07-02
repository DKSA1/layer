<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 10-11-18
 * Time: 13:51
 */

namespace layer\core;

use layer\core\config\Configuration;
use layer\core\http\Request;
use layer\core\http\Response;
use layer\core\manager\ControllerManager;
use layer\core\manager\FilterManager;
use layer\core\manager\LogManager;
use layer\core\manager\MapManager;
use layer\core\manager\RouteManager;
use layer\core\manager\ViewManager;

class App
{
    private $startTime;
    /**
     * @var App $instance
     */
    private static $instance;
    /**
     * @var callable $appErrorCallback
     */
    private $appErrorCallback;
    /**
     * @var callable $appFinallyCallback
     */
    private $appFinallyCallback;
    /**
     * @var Request
     */
    private $request;
    /**
     * @var Response
     */
    private $response;
    /**
     * @var MapManager
     */
    private $mapManager;
    /**
     * @var FilterManager
     */
    private $filterManager;
    /**
     * @var LogManager
     */
    private $logManager;
    /**
     * @var RouteManager
     */
    private $routeManager;
    /**
     * @var ControllerManager
     */
    private $controllerManager;
    /**
     * @var ViewManager
     */
    private $viewManager;

    public static function getInstance() : App
    {
        if(self::$instance == null) self::$instance = new App();
        return self::$instance;
    }

    private function __construct()
    {
        define("PATH", rtrim($_SERVER['SCRIPT_FILENAME'],"index.php"));

        $this->request = Request::getInstance();
        $this->response = Response::getInstance();

        Configuration::load();

        $this->registerGlobals();

        $this->initManagers();

    }

    private function initManagers() {
        try {
            $this->mapManager = MapManager::getInstance();
            if(Configuration::get('environment/'.Configuration::$environment.'/buildRoutesMap')) {
                $this->mapManager->build();
            } else {
                $this->mapManager->load();
            }
            $this->filterManager = FilterManager::getInstance($this->mapManager->getMap()['shared']['filters'], $this->mapManager->getMap()['shared']['globals']);
            $this->routeManager = RouteManager::getInstance($this->mapManager->getRoutes());
            $this->controllerManager = ControllerManager::getInstance($this->mapManager->getMap()['controllers']);
            // TODO : instantiate only if route hasView or hasLayout
            $this->viewManager = ViewManager::getInstance($this->mapManager->getMap()['shared']['views']);
            $this->logManager = LogManager::getInstance($this->request);
        } catch(\Exception $e) {

        }
    }

    public function handleRequest() {

        $this->startTime = microtime(true);

        $this->routeManager->run($this->request->getBaseUrl(), $this->request->getRequestMethod());
        // TODO : return route

        // TODO : execute controllerManager with filterManager and active route

        // TODO : handle result if it's an api or site

        // TODO : send response
        $this->response->sendResponse();

    }

    private function registerGlobals(){

        $appContext = Configuration::get("globals");
        foreach ($appContext as $c => $v) {
            if(is_array($v)) $v = json_encode($v);
            if(!defined(strtoupper($c))) define(strtoupper($c),$v);
        }
        define("APP_ROOT",rtrim($_SERVER["PHP_SELF"],"index.php"));
        // TODO : change with PATH_
    }

    /**
     * @param $appErrorCallback
     */
    public function setAppErrorCallback($appErrorCallback)
    {
        if(is_callable($appErrorCallback)) {
            $this->appErrorCallback = $appErrorCallback;
        }
    }

    /**
     * @param $appFinallyCallback
     */
    public function setAppFinallyCallback($appFinallyCallback)
    {
        if(is_callable($appFinallyCallback)) {
            $this->appFinallyCallback = $appFinallyCallback;
        }

    }
}