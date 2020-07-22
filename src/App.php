<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 10-11-18
 * Time: 13:51
 */

namespace rloris\layer;

use rloris\layer\core\config\Configuration;
use rloris\layer\core\error\EForwardName;
use rloris\layer\core\error\EForwardURL;
use rloris\layer\core\error\ELayer;
use rloris\layer\core\error\ERedirect;
use rloris\layer\core\http\IHttpHeaders;
use rloris\layer\core\http\Request;
use rloris\layer\core\http\Response;
use rloris\layer\core\manager\ControllerManager;
use rloris\layer\core\manager\FilterManager;
use rloris\layer\core\manager\MapManager;
use rloris\layer\core\manager\RouteManager;
use rloris\layer\core\manager\ViewManager;
use rloris\layer\core\mvc\controller\CoreController;
use rloris\layer\utils\Logger;

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
        Configuration::load($config);
        $this->init();
    }

    private function init() {
        try {
            $this->registerGlobals();
            $this->mapManager = new MapManager();
            if(Configuration::environment('build')) {
                $this->mapManager->build();
            } else {
                $this->mapManager->load();
            }
            $this->filterManager = new FilterManager($this->mapManager->getMap()['shared']['filters'], $this->mapManager->getMap()['shared']['globals']);
            $this->routeManager = new RouteManager($this->mapManager->getRoutes());
            // init request and response
            $this->request = new Request($this->routeManager);
            $this->response = new Response($this->request);
            $this->controllerManager = new ControllerManager($this->mapManager->getMap()['controllers'], $this->filterManager, $this->response);
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

    // handle the request
    private function handleRequest($method, $url, $params = null, $controller = null, $action = null) {
        if($controller === null && $action === null) {
            // url route
            $route = $this->routeManager->match($method, $url, $params);
            $controller = $route->getControllerNamespace();
            $action = $route->getControllerAction();
            $params = $route->getParams();
        }
        $viewManager = !$this->controllerManager->isApiController($controller) ? $this->getViewManager() : null;
        $this->controllerManager->run($controller, $action, $params, $viewManager);
    }

    private function run($method, $url, $params = null, $controller = null, $action = null) {
        try
        {
            $this->handleRequest($method, $url, $params, $controller, $action);
        }
        catch(EForwardURL $e)
        {
            // url forward
            $this->response->setResponseCode($e->getCode());
            $this->run($e->getMethod(), $e->getInternalRoute(), $e->getParams());
        }
        catch(EForwardName $e)
        {
            // named forward
            $this->response->setResponseCode($e->getCode());
            $this->run(null, null, $e->getParams(), $e->getController(), $e->getAction());
        }
        catch(ERedirect $e)
        {
            // redirect
            $this->response->putHeader(IHttpHeaders::Location, $e->getLocation());
            $this->response->setResponseCode($e->getCode());
        }
        catch(ELayer $e)
        {
            // error
            $this->response->setResponseCode($e->getCode());
            if($this->routeManager->isApiUrl($url)) {
                // api
                $route = "api/".$e->getCode();
            } else {
                $route = $e->getCode();
            }
            try {
                $this->handleRequest('*', $route, ['e' => $e]);
            } catch(ELayer $exception) {
                $this->handleRequest('*', $this->routeManager->isApiUrl($url) ? 'api' : '',  ['e' => $e]);
            }
        }
    }

    public function execute()
    {
        try {
            $this->startTime = microtime(true);
            $this->run($this->request->getRequestMethod(), $this->request->getBaseUrl());
            $this->response->sendResponse();
            return $this->response->getResponseCode();
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