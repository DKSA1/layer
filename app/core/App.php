<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 10-11-18
 * Time: 13:51
 */

namespace layer\core;

use layer\core\config\Configuration;
use layer\core\error\EForward;
use layer\core\error\ELayer;
use layer\core\error\ERedirect;
use layer\core\http\Request;
use layer\core\http\Response;
use layer\core\manager\ControllerManager;
use layer\core\manager\FilterManager;
use layer\core\manager\MapManager;
use layer\core\manager\RouteManager;
use layer\core\manager\ViewManager;
use layer\core\mvc\controller\CoreController;
use layer\core\utils\Logger;

class App
{
    private $startTime;
    /**
     * @var App $instance
     */
    private static $instance;
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

    public static function getInstance(string $config) : App
    {
        if(self::$instance == null && file_exists($config)) self::$instance = new App($config);
        return self::$instance;
    }

    private function __construct(string $config)
    {
        define("APP_ROOT", rtrim($_SERVER["PHP_SELF"],"index.php"));
        define("APP_PATH", rtrim($_SERVER['SCRIPT_FILENAME'],"index.php"));
        $this->request = Request::getInstance();
        $this->response = Response::getInstance();
        Configuration::load($config);
        $this->init();
    }

    private function init() {
        try {
            $this->registerGlobals();
            $this->mapManager = new MapManager();
            if(Configuration::get('environment/'.Configuration::$environment.'/build')) {
                $this->mapManager->build();
            } else {
                $this->mapManager->load();
            }
            $this->filterManager = new FilterManager($this->mapManager->getMap()['shared']['filters'], $this->mapManager->getMap()['shared']['globals']);
            $this->routeManager = new RouteManager($this->mapManager->getRoutes());
            $this->controllerManager = new ControllerManager($this->mapManager->getMap()['controllers'], $this->filterManager);
            $this->setup();
        } catch(\Exception $e) {
            echo "An error occurred during the app initialisation process: {$e->getMessage()}\n";
        }
    }

    // set static properties
    private function setup() {
        // FILTERS & CONTROLLERS
        $reflection = new \ReflectionClass(CoreController::class);
        // set filterManager
        if ($reflection->hasProperty("filterManager")) {
            $fm = $reflection->getProperty("filterManager");
            $fm->setAccessible(true);
            $fm->setValue(null, $this->filterManager);
            $fm->setAccessible(false);
        }
        // set request
        if($reflection->hasProperty("request")) {
            $req = $reflection->getProperty("request");
            $req->setAccessible(true);
            $req->setValue(null, $this->request);
            $req->setAccessible(false);
        }
        // set response
        if($reflection->hasProperty("response")) {
            $res = $reflection->getProperty("response");
            $res->setAccessible(true);
            $res->setValue(null, $this->response);
            $res->setAccessible(false);
        }
        // LOGGER
        $reflection = new \ReflectionClass(Logger::class);
        // set request
        if($reflection->hasProperty("request")) {
            $req = $reflection->getProperty("request");
            $req->setAccessible(true);
            $req->setValue(null, $this->request);
            $req->setAccessible(false);
        }
    }

    // create view manager only if needed
    private function getViewManager() {
        if(!$this->viewManager) $this->viewManager = new ViewManager($this->mapManager->getMap()['shared']['views']);
        return $this->viewManager;
    }

    private function handleRequest($method, $url, $params = null) {
        $route = $this->routeManager->match($method, $url, $params);
        $viewManager = !$this->controllerManager->isApiController($route->getControllerNamespace()) ? $this->getViewManager() : null;
        $this->controllerManager->run($route, $this->response, $viewManager);
    }

    private function run($method, $url, $params = null) {
        try {
            $this->handleRequest($method, $url, $params);
        } catch(EForward $e) {
            // forward
            $this->run($e->getMethod(), $e->getInternalRoute(), $e->getParams());
        } catch(ERedirect $e) {
            // redirect
        } catch(ELayer $e) {
            // error
            $this->response->setResponseCode($e->getCode());
            if($this->routeManager->isApiUrl($url)) {
                // api
                $route = trim(Configuration::get('environment/'.Configuration::$environment.'/apiRouteTemplate'), "/")."/".$e->getCode();
            } else {
                $route = trim(Configuration::get('environment/'.Configuration::$environment.'/routeTemplate'), "/")."/".$e->getCode();
            }
            echo $url."  ".$route;
            $this->handleRequest('*', $route, ['e' => $e]);
        }
    }

    public function execute() : bool
    {
        try {
            $this->startTime = microtime(true);
            $this->run($this->request->getRequestMethod(), $this->request->getBaseUrl());
            $this->response->sendResponse();
            return true;
        } catch (ELayer $e) {
            echo "An error occurred during the execution process: {$e->getMessage()}\n";
            return false;
        }
    }

    private function registerGlobals(){
        $appContext = Configuration::get("globals");
        foreach ($appContext as $c => $v) {
            if(is_array($v)) $v = json_encode($v);
            if(!defined(strtoupper($c))) define(strtoupper($c),$v);
        }
    }
}